<?php

namespace App\Actions\Admin;

use App\Enums\CustomerOrderEmailType;
use App\Models\Order;
use App\Models\User;
use App\Services\Discord\DiscordNotifier;
use App\Services\Mail\BoosterEmailNotifier;
use App\Services\Mail\CustomerOrderEmailNotifier;
use App\Services\Orders\OrderFinancialsService;
use App\Services\Orders\OrderPricingPayloadService;
use App\Support\AdminManualOrderData;
use App\Support\BoostingCatalog;
use App\Support\GameCatalog;
use App\Support\OrderLifecycleMetadata;
use App\Support\OrderStatus;
use App\Support\Pricing\PricingEngineManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StoreManualOrderAction
{
    public function __construct(
        protected DiscordNotifier $discordNotifier,
        protected CustomerOrderEmailNotifier $customerOrderEmailNotifier,
        protected BoosterEmailNotifier $boosterEmailNotifier,
        protected OrderFinancialsService $orderFinancialsService,
        protected OrderPricingPayloadService $orderPricingPayloadService,
        protected PricingEngineManager $pricingEngine,
        protected GameCatalog $gameCatalog,
    ) {}

    public function execute(User $customer, ?User $booster, User $admin, array $data): Order
    {
        $manualOrderPayload = AdminManualOrderData::orderPayload($data);
        $authoritativePricingPreview = $this->authoritativePricingPreview(
            $this->orderPricingPayloadService->payloadFromAdminInput($data)
        );
        $manualPriceCents = AdminManualOrderData::manualPriceCents($data['price'] ?? null);
        $priceCents = $manualPriceCents
            ?? ($authoritativePricingPreview['valid'] ? $authoritativePricingPreview['priceCents'] : 0);
        $financials = $this->orderFinancialsService->fromOriginalPriceCents(
            $priceCents,
            0,
            Order::configuredBoosterPayoutPercentage(),
        );
        $currency = strtoupper((string) ($data['currency'] ?? 'USD'));
        $contactMethod = $data['contact_method'] ?? 'email';
        $status = $booster ? OrderStatus::IN_PROGRESS : OrderStatus::PENDING;
        $details = $this->buildDetails($data, $manualOrderPayload, $currency, $financials, $authoritativePricingPreview, $manualPriceCents);
        $metadata = $this->buildMetadata($customer, $admin, $contactMethod, $currency, $financials, $authoritativePricingPreview, $manualPriceCents);
        $metadata['game'] = [
            'slug' => BoostingCatalog::gameSlugFromPayload($manualOrderPayload),
            'name' => BoostingCatalog::gameName(BoostingCatalog::gameSlugFromPayload($manualOrderPayload)),
        ];

        if ($booster) {
            $metadata = OrderLifecycleMetadata::record($metadata, 'assigned', OrderStatus::PENDING, OrderStatus::IN_PROGRESS, [
                'source' => 'admin_manual_order',
                'actor_id' => $admin->getKey(),
                'next_step' => 'Work is underway in the order dashboard.',
            ]);
        }

        return DB::transaction(function () use ($booster, $contactMethod, $currency, $customer, $data, $details, $financials, $manualOrderPayload, $metadata, $status) {
            $gameSlug = BoostingCatalog::gameSlugFromPayload($manualOrderPayload);
            $attributes = [
                'user_id' => $customer->id,
                'booster_id' => $booster?->id,
                'order_number' => (string) Str::orderedUuid(),
                'product' => $manualOrderPayload['orderType'] ?? $data['product'],
                'status' => $status,
                'payment_status' => $data['payment_status'],
                'price_cents' => $financials['price_cents'],
                'original_price_cents' => $financials['original_price_cents'],
                'discount_amount' => $financials['discount_amount'],
                'booster_payout_rate' => $financials['booster_payout_rate'],
                'booster_payout_cents' => $financials['booster_payout_cents'],
                'booster_payout_basis_cents' => $financials['booster_payout_basis_cents'],
                'currency' => $currency,
                'details' => $details,
                'metadata' => $metadata,
                'contact_method' => $contactMethod,
                'whatsapp' => $data['whatsapp'] ?? null,
                'discord' => $data['discord'] ?? null,
                'is_custom' => true,
                'paid_at' => $data['payment_status'] === 'paid' ? now() : null,
                'assigned_at' => $booster ? now() : null,
            ];

            if (Schema::hasColumn('orders', 'game_id')) {
                $attributes['game_id'] = $this->gameCatalog->gameId($gameSlug);
            }

            if (Schema::hasColumn('orders', 'service_id')) {
                $attributes['service_id'] = $this->gameCatalog->serviceId($gameSlug, $manualOrderPayload['orderType'] ?? $data['product'] ?? null);
            }

            $order = Order::create($attributes)->load(['user', 'booster']);

            DB::afterCommit(fn () => $this->discordNotifier->queueOrderCreated($order));

            if ($booster) {
                $this->customerOrderEmailNotifier->queue(CustomerOrderEmailType::ASSIGNED, $order, [
                    'previous_status' => OrderStatus::PENDING,
                    'current_status' => OrderStatus::IN_PROGRESS,
                ]);
                $this->boosterEmailNotifier->queueOrderAssignedByAdmin($order);
            }

            return $order;
        });
    }

    protected function authoritativePricingPreview(array $pricingPayload): array
    {
        if (! $this->orderPricingPayloadService->canAuthoritativelyPrice($pricingPayload)) {
            return [
                'available' => false,
                'valid' => false,
                'priceCents' => null,
                'total' => null,
                'validationErrors' => [],
            ];
        }

        $result = $this->pricingEngine->calculate($pricingPayload);
        $validationErrors = $result['validationErrors'] ?? [];
        $isValid = $validationErrors === [];
        $total = $isValid ? (float) data_get($result, 'pricing.total', $result['finalPrice'] ?? 0) : null;

        return [
            'available' => true,
            'valid' => $isValid,
            'priceCents' => $total !== null ? (int) round($total * 100) : null,
            'total' => $total,
            'validationErrors' => $validationErrors,
            'payload' => $result,
        ];
    }

    protected function buildDetails(
        array $data,
        array $manualOrderPayload,
        string $currency,
        array $financials,
        array $authoritativePricingPreview,
        ?int $manualPriceCents,
    ): array {
        $gameSlug = BoostingCatalog::normalizeGameSlug($data['game'] ?? $manualOrderPayload['gameSlug'] ?? null);
        $game = $this->gameCatalog->gameName($gameSlug);
        $service = $manualOrderPayload['orderType'] ?? $data['product'];
        $appliedPrice = round($financials['price_cents'] / 100, 2);
        $orderPricing = [
            'currency' => $currency,
            'source' => $manualPriceCents !== null ? 'admin-manual-override' : 'authoritative-preview-fallback',
            'basePrice' => $appliedPrice,
            'subtotal' => $appliedPrice,
            'discountAmount' => 0,
            'originalTotal' => round($financials['original_price_cents'] / 100, 2),
            'finalPrice' => $appliedPrice,
            'total' => $appliedPrice,
            'boosterPayoutBasis' => round($financials['booster_payout_basis_cents'] / 100, 2),
        ];

        if ($authoritativePricingPreview['available']) {
            $orderPricing['authoritativePreviewAvailable'] = true;
            $orderPricing['authoritativePreviewValid'] = (bool) $authoritativePricingPreview['valid'];
            $orderPricing['authoritativePreviewTotal'] = $authoritativePricingPreview['total'];
            $orderPricing['authoritativePreviewValidationErrors'] = $authoritativePricingPreview['validationErrors'] ?? [];
        }

        $orderPayload = array_merge($manualOrderPayload, [
            'gameSlug' => $gameSlug,
            'game' => $game,
            'pricing' => $orderPricing,
        ]);

        return array_filter([
            'game' => $game,
            'gameSlug' => $gameSlug,
            'service' => $service,
            'from' => $manualOrderPayload['currentDivision'] ?? null,
            'to' => $manualOrderPayload['desiredDivision'] ?? null,
            'currentRR' => $manualOrderPayload['currentRR'] ?? null,
            'averageRR' => $manualOrderPayload['averageRR'] ?? null,
            'region' => $manualOrderPayload['region'] ?? null,
            'platform' => $manualOrderPayload['platform'] ?? null,
            'accountType' => $manualOrderPayload['accountType'] ?? null,
            'addons' => $manualOrderPayload['addons'] ?? [],
            'specificAgents' => $manualOrderPayload['specificAgents'] ?? [],
            'oneTrickAgent' => $manualOrderPayload['oneTrickAgent'] ?? [],
            'numberOfWins' => $manualOrderPayload['numberOfWins'] ?? null,
            'numberOfPlacementGames' => $manualOrderPayload['numberOfPlacementGames'] ?? null,
            'notes' => $data['notes'] ?? null,
            'adminNotes' => $data['notes'] ?? null,
            'source' => 'admin-custom-order',
            'orderCategory' => 'manual',
            'adminOverride' => [
                'customerRestrictionsBypassed' => true,
                'manualPriceApplied' => $manualPriceCents !== null,
            ],
            'order' => $orderPayload,
        ], fn ($value) => ! ($value === null || $value === ''));
    }

    protected function buildMetadata(
        User $customer,
        User $admin,
        string $contactMethod,
        string $currency,
        array $financials,
        array $authoritativePricingPreview,
        ?int $manualPriceCents,
    ): array {
        $finalTotal = round($financials['price_cents'] / 100, 2);

        return [
            'customer' => [
                'firstName' => $customer->first_name,
                'lastName' => $customer->last_name,
                'email' => $customer->email,
            ],
            'contactMethod' => $contactMethod,
            'paymentMethod' => 'admin-manual',
            'paymentProvider' => 'admin-manual',
            'createdByAdminId' => $admin->id,
            'createdByAdminName' => $admin->name,
            'source' => 'admin-custom-order',
            'pricing' => [
                'currency' => $currency,
                'source' => $manualPriceCents !== null ? 'admin-manual-override' : 'authoritative-preview-fallback',
                'subtotal' => $finalTotal,
                'discountAmount' => 0,
                'originalTotal' => round($financials['original_price_cents'] / 100, 2),
                'finalTotal' => $finalTotal,
                'boosterPayoutBasis' => round($financials['booster_payout_basis_cents'] / 100, 2),
                'authoritativePreviewAvailable' => (bool) ($authoritativePricingPreview['available'] ?? false),
                'authoritativePreviewValid' => (bool) ($authoritativePricingPreview['valid'] ?? false),
                'authoritativePreviewTotal' => $authoritativePricingPreview['total'] ?? null,
            ],
            'adminOverride' => [
                'enabled' => true,
                'customerRestrictionsBypassed' => true,
                'pricingMode' => $manualPriceCents !== null ? 'manual-price' : 'authoritative-preview-fallback',
                'manualPriceApplied' => $manualPriceCents !== null,
                'manualPriceCents' => $manualPriceCents,
                'manualPrice' => $manualPriceCents !== null ? round($manualPriceCents / 100, 2) : null,
                'authoritativePreviewAvailable' => (bool) ($authoritativePricingPreview['available'] ?? false),
                'authoritativePreviewValid' => (bool) ($authoritativePricingPreview['valid'] ?? false),
                'authoritativePreviewTotal' => $authoritativePricingPreview['total'] ?? null,
                'authoritativePreviewValidationErrors' => $authoritativePricingPreview['validationErrors'] ?? [],
            ],
        ];
    }
}
