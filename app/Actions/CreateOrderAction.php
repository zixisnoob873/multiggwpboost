<?php

namespace App\Actions;

use App\Data\Payments\PaymentCheckoutData;
use App\Models\Order;
use App\Services\Discord\DiscordNotifier;
use App\Services\Orders\OrderFinancialsService;
use App\Services\PromoCodeService;
use App\Support\BoostingCatalog;
use App\Support\GameCatalog;
use App\Support\Logging\AppEventLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateOrderAction
{
    public function __construct(
        protected DiscordNotifier $discordNotifier,
        protected PromoCodeService $promoCodeService,
        protected OrderFinancialsService $orderFinancialsService,
        protected AppEventLogger $eventLogger,
        protected GameCatalog $gameCatalog,
    ) {}

    public function execute(int $userId, PaymentCheckoutData $checkoutData, array $paymentAttributes = []): Order
    {
        $orderPayload = $this->sanitizeOrderPayloadForStorage($checkoutData->orderPayload);
        $baseOrderPayload = $this->sanitizeOrderPayloadForStorage(
            $checkoutData->baseOrderPayload !== [] ? $checkoutData->baseOrderPayload : $checkoutData->orderPayload
        );
        $paymentStatus = (string) ($paymentAttributes['payment_status'] ?? 'pending');
        $customerNotes = $this->customerNotes($checkoutData->requestData['customerNotes'] ?? null);
        $baseMetadata = array_replace_recursive($checkoutData->metadata, [
            'game' => [
                'slug' => BoostingCatalog::gameSlugFromPayload($orderPayload),
                'name' => BoostingCatalog::gameName(BoostingCatalog::gameSlugFromPayload($orderPayload)),
            ],
            'customer' => [
                'firstName' => $checkoutData->requestData['firstName'],
                'lastName' => $checkoutData->requestData['lastName'],
                'email' => $checkoutData->requestData['email'],
            ],
            'contactMethod' => $checkoutData->requestData['contactMethod'],
            'paymentMethod' => $checkoutData->paymentMethod,
            'paymentProvider' => $checkoutData->paymentMethod,
            'checkout' => array_filter([
                'contactMethod' => $checkoutData->requestData['contactMethod'],
                'email' => $checkoutData->requestData['email'] ?? null,
                'whatsapp' => $checkoutData->requestData['whatsapp'] ?? null,
                'discord' => $checkoutData->requestData['discord'] ?? null,
                'customerNotes' => $customerNotes,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        ]);

        return DB::transaction(function () use ($userId, $checkoutData, $orderPayload, $baseOrderPayload, $paymentAttributes, $paymentStatus, $baseMetadata, $customerNotes) {
            $discountAmount = $checkoutData->discountAmount;
            $promoCode = null;
            $promoResult = null;

            if ($checkoutData->promoCodeId) {
                $promoResult = $this->promoCodeService->consumeResolvedCode(
                    $checkoutData->promoCodeId,
                    $baseOrderPayload,
                    $userId,
                );
                $promoCode = $promoResult->promoCode;
                $discountAmount = $promoResult->discountAmount;
                $authoritativeOrderPayload = $this->sanitizeOrderPayloadForStorage($promoResult->originalOrderPayload);

                if ($this->toCents($promoResult->orderAmount) !== $this->toCents($checkoutData->subtotal)
                    || $this->toCents($promoResult->discountedTotal) !== $checkoutData->priceCents
                    || ! $this->payloadMatches($authoritativeOrderPayload, $orderPayload)
                ) {
                    throw ValidationException::withMessages([
                        'promoCode' => ['This promo code changed before payment completed. Please contact support before we finalize the order.'],
                    ]);
                }

                $orderPayload = $authoritativeOrderPayload;
            }

            $hasAuthoritativeOriginalPrice = $checkoutData->subtotal > 0 || $checkoutData->promoCodeId !== null || $discountAmount > 0;

            if ($hasAuthoritativeOriginalPrice) {
                $originalPriceCents = $checkoutData->subtotal > 0
                    ? max(0, (int) round($checkoutData->subtotal * 100))
                    : max(0, $checkoutData->priceCents + (int) round($discountAmount * 100));

                $financials = $this->orderFinancialsService->fromOriginalPriceCents(
                    $originalPriceCents,
                    $discountAmount,
                    Order::configuredBoosterPayoutPercentage(),
                );
            } else {
                $financials = $this->orderFinancialsService->fromCustomerPriceCents(
                    $checkoutData->priceCents,
                    0,
                    Order::configuredBoosterPayoutPercentage(),
                );
            }

            if ($checkoutData->promoCodeId && (int) $financials['price_cents'] !== $checkoutData->priceCents) {
                throw ValidationException::withMessages([
                    'promoCode' => ['This promo code changed before payment completed. Please contact support before we finalize the order.'],
                ]);
            }

            $metadata = array_merge($baseMetadata, $paymentAttributes['metadata'] ?? []);

            if ($promoCode) {
                $metadata['promoCode'] = [
                    'id' => $promoCode->id,
                    'code' => $promoCode->code,
                    'type' => $promoCode->type,
                    'value' => (float) $promoCode->value,
                    'originalTotal' => $promoResult?->orderAmount,
                    'finalTotal' => $promoResult?->discountedTotal,
                    'discountAmount' => round($discountAmount, 2),
                    'managedAddons' => $promoResult?->promoManagedAddons ?? [],
                    'addedAddons' => $promoResult?->promoAddedAddons ?? [],
                    'addonAdjustments' => $promoResult?->promoAddonAdjustments ?? [],
                ];
            }

            $metadata['pricing'] = array_merge($metadata['pricing'] ?? [], [
                'subtotal' => round($financials['original_price_cents'] / 100, 2),
                'originalTotal' => round($financials['original_price_cents'] / 100, 2),
                'discountAmount' => round($financials['discount_amount'], 2),
                'finalTotal' => round($financials['price_cents'] / 100, 2),
                'boosterPayoutBasis' => round($financials['booster_payout_basis_cents'] / 100, 2),
            ]);

            $gameSlug = BoostingCatalog::gameSlugFromPayload($orderPayload);
            $addons = BoostingCatalog::normalizeAddonsForGame($orderPayload['addons'] ?? [], $gameSlug);
            $serviceReference = $orderPayload['serviceSlug'] ?? $orderPayload['orderType'] ?? $orderPayload['serviceType'] ?? null;
            $attributes = [
                'user_id' => $userId,
                'order_number' => (string) Str::orderedUuid(),
                'promo_code_id' => $promoCode?->id,
                'product' => $orderPayload['orderType'] ?? 'Rank Boosting',
                'status' => 'Pending',
                'payment_status' => $paymentStatus,
                'price_cents' => $financials['price_cents'],
                'original_price_cents' => $financials['original_price_cents'],
                'discount_amount' => $financials['discount_amount'],
                'booster_payout_rate' => $financials['booster_payout_rate'],
                'booster_payout_cents' => $financials['booster_payout_cents'],
                'booster_payout_basis_cents' => $financials['booster_payout_basis_cents'],
                'currency' => 'USD',
                'details' => [
                    'game' => $this->gameCatalog->gameName($gameSlug),
                    'gameSlug' => $gameSlug,
                    'service' => $orderPayload['orderType'] ?? $orderPayload['serviceType'] ?? null,
                    'serviceSlug' => $orderPayload['serviceSlug'] ?? null,
                    'addons' => $addons,
                    'selectedOptions' => data_get($metadata, 'calculator.selectedOptions', $orderPayload['selectedOptions'] ?? []),
                    'customerNotes' => $customerNotes,
                    'specificAgents' => BoostingCatalog::normalizeSpecificAgents($orderPayload['specificAgents'] ?? []),
                    'oneTrickAgent' => BoostingCatalog::normalizeOneTrickAgent($orderPayload['oneTrickAgent'] ?? []),
                    'order' => $orderPayload,
                ],
                'metadata' => $metadata,
                'contact_method' => $checkoutData->requestData['contactMethod'],
                'whatsapp' => $checkoutData->requestData['whatsapp'] ?? null,
                'discord' => $checkoutData->requestData['discord'] ?? null,
                'stripe_session_id' => $paymentAttributes['stripe_session_id'] ?? null,
                'payment_reference' => $paymentAttributes['payment_reference'] ?? null,
                'paid_at' => $paymentAttributes['paid_at'] ?? ($paymentStatus === 'paid' ? now() : null),
            ];

            if (Schema::hasColumn('orders', 'game_id')) {
                $attributes['game_id'] = $this->gameCatalog->gameId($gameSlug);
            }

            if (Schema::hasColumn('orders', 'service_id')) {
                $attributes['service_id'] = $this->gameCatalog->serviceId($gameSlug, $serviceReference);
            }

            $order = Order::create($attributes);

            DB::afterCommit(fn () => $this->discordNotifier->queueOrderCreated($order));
            $this->eventLogger->order('order.created', $order, null, [
                'customer_id' => $userId,
                'payment_provider' => (string) ($metadata['paymentProvider'] ?? $checkoutData->paymentMethod),
                'promo_code_id' => $promoCode?->id,
                'discount_amount' => round($discountAmount, 2),
                'promo_managed_addons' => $promoResult?->promoManagedAddons ?? [],
                'promo_added_addons' => $promoResult?->promoAddedAddons ?? [],
            ]);

            return $order;
        });
    }

    protected function sanitizeOrderPayloadForStorage(array $payload): array
    {
        unset(
            $payload['promoCode'],
            $payload['promoAddonAdjustments'],
            $payload['promoManagedAddons'],
            $payload['promoAddedAddons'],
        );

        return BoostingCatalog::sanitizeOrderPayload($payload);
    }

    protected function payloadMatches(array $left, array $right): bool
    {
        return $this->payloadSignature($left) === $this->payloadSignature($right);
    }

    protected function payloadSignature(array $payload): string
    {
        $payload = BoostingCatalog::sanitizeOrderPayload($payload);

        return json_encode([
            'serviceType' => BoostingCatalog::normalizeServiceType($payload['serviceType'] ?? $payload['orderType'] ?? null),
            'gameSlug' => BoostingCatalog::gameSlugFromPayload($payload),
            'currentDivision' => (string) ($payload['currentDivision'] ?? $payload['currentRank'] ?? ''),
            'desiredDivision' => (string) ($payload['desiredDivision'] ?? $payload['targetDivision'] ?? $payload['targetRank'] ?? ''),
            'currentRR' => is_numeric($payload['currentRR'] ?? null) ? (int) $payload['currentRR'] : null,
            'avgRRPerWin' => (string) ($payload['avgRRPerWin'] ?? $payload['averageRR'] ?? ''),
            'region' => (string) ($payload['region'] ?? ''),
            'platform' => (string) ($payload['platform'] ?? ''),
            'boostMode' => (string) ($payload['boostMode'] ?? $payload['accountType'] ?? ''),
            'numberOfWins' => is_numeric($payload['numberOfWins'] ?? null) ? (int) $payload['numberOfWins'] : null,
            'numberOfPlacementGames' => is_numeric($payload['numberOfPlacementGames'] ?? null) ? (int) $payload['numberOfPlacementGames'] : null,
            'addons' => BoostingCatalog::normalizeAddonsForGame(
                $payload['addons'] ?? $payload['selectedAddons'] ?? [],
                BoostingCatalog::gameSlugFromPayload($payload)
            ),
            'specificAgents' => BoostingCatalog::normalizeSpecificAgents($payload['specificAgents'] ?? []),
            'oneTrickAgent' => BoostingCatalog::normalizeOneTrickAgent($payload['oneTrickAgent'] ?? []),
        ], JSON_THROW_ON_ERROR);
    }

    protected function toCents(float $amount): int
    {
        return max(0, (int) round($amount * 100));
    }

    protected function customerNotes(mixed $value): ?string
    {
        if (! is_scalar($value) && ! $value instanceof \Stringable) {
            return null;
        }

        $notes = trim((string) $value);

        return $notes !== '' ? Str::limit($notes, 1000, '') : null;
    }
}
