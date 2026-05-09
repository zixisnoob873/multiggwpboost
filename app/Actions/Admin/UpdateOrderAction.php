<?php

namespace App\Actions\Admin;

use App\Models\Order;
use App\Models\User;
use App\Services\Mail\BoosterEmailNotifier;
use App\Services\OrderAssignmentService;
use App\Services\Orders\OrderFinancialsService;
use App\Services\Orders\OrderPricingPayloadService;
use App\Support\AdminManualOrderData;
use App\Support\BoostingCatalog;
use App\Support\OrderLifecycleMetadata;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateOrderAction
{
    public function __construct(
        protected OrderAssignmentService $orderAssignmentService,
        protected BoosterEmailNotifier $boosterEmailNotifier,
        protected OrderFinancialsService $orderFinancialsService,
        protected OrderPricingPayloadService $orderPricingPayloadService,
    ) {}

    public function execute(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $previousBoosterId = $lockedOrder->booster_id;
            $product = (string) ($data['product'] ?? $lockedOrder->product ?? 'Rank Boosting');
            $customerPriceCents = array_key_exists('price', $data)
                ? (int) round(((float) $data['price']) * 100)
                : $lockedOrder->customerPriceCents();
            $currency = strtoupper((string) ($data['currency'] ?? $lockedOrder->currency ?? 'USD'));
            $customer = null;
            if (array_key_exists('user_id', $data) && $data['user_id']) {
                $customer = User::query()->find($data['user_id']);
            }

            $existingMetadata = $this->normalizeStructuredFields($lockedOrder->metadata);
            $incomingMetadata = array_key_exists('metadata', $data)
                ? $this->normalizeStructuredFields($data['metadata'])
                : [];
            $metadata = array_key_exists('metadata', $data)
                ? $this->mergeStructuredValues($existingMetadata, $incomingMetadata)
                : $existingMetadata;
            if ($customer) {
                $metadata['customer'] = [
                    'firstName' => $customer->first_name,
                    'lastName' => $customer->last_name,
                    'email' => $customer->email,
                ];
            }

            $existingDetails = $this->normalizeStructuredFields($lockedOrder->details);
            $incomingDetails = array_key_exists('details', $data)
                ? $this->normalizeStructuredFields($data['details'])
                : [];
            $mergedDetails = array_key_exists('details', $data)
                ? $this->mergeStructuredValues($existingDetails, $incomingDetails)
                : $existingDetails;
            $isAdminOverrideOrder = $this->isAdminOverrideOrder($lockedOrder, $metadata);
            $details = $isAdminOverrideOrder
                ? $this->normalizeAdminOverrideDetails($mergedDetails, $product)
                : $this->syncProductDetails(BoostingCatalog::sanitizeOrderDetails($mergedDetails), $product);

            if ($isAdminOverrideOrder) {
                [$details, $metadata, $financials] = $this->applyAdminOverridePricing(
                    $lockedOrder,
                    $details,
                    $metadata,
                    $product,
                    $customerPriceCents,
                    $currency
                );
            } else {
                [$details, $metadata, $financials] = $this->applyAuthoritativePricing(
                    $lockedOrder,
                    $details,
                    $metadata,
                    $product,
                    $customerPriceCents
                );
            }

            $previousStatus = $lockedOrder->status;
            $assignmentAttributes = array_key_exists('booster_id', $data)
                ? $this->orderAssignmentService->adminAssignmentAttributes($lockedOrder, $data['booster_id'], $data['status'] ?? null)
                : ['status' => $data['status']];
            $nextStatus = (string) ($assignmentAttributes['status'] ?? $lockedOrder->status);
            $metadata = $this->withStatusTransitionMetadata($lockedOrder, $metadata, $previousStatus, $nextStatus, $data);

            $lockedOrder->forceFill(array_merge([
                'user_id' => $data['user_id'] ?? $lockedOrder->user_id,
                'payment_status' => $data['payment_status'] ?? $lockedOrder->payment_status,
                'product' => $product,
                'price_cents' => $financials['price_cents'],
                'original_price_cents' => $financials['original_price_cents'],
                'discount_amount' => $financials['discount_amount'],
                'booster_payout_rate' => $financials['booster_payout_rate'],
                'booster_payout_cents' => $financials['booster_payout_cents'],
                'booster_payout_basis_cents' => $financials['booster_payout_basis_cents'],
                'currency' => $currency,
                'details' => $details,
                'metadata' => $metadata,
                'paid_at' => ($data['payment_status'] ?? $lockedOrder->payment_status) === 'paid'
                    ? ($lockedOrder->paid_at ?? now())
                    : null,
            ], $assignmentAttributes))->save();

            $updatedOrder = $lockedOrder->refresh()->load(['user', 'booster']);

            if (
                array_key_exists('booster_id', $data)
                && $updatedOrder->booster_id !== null
                && (int) $previousBoosterId !== (int) $updatedOrder->booster_id
            ) {
                $this->boosterEmailNotifier->queueOrderAssignedByAdmin($updatedOrder);
            }

            return $updatedOrder;
        }, 3);
    }

    protected function normalizeStructuredFields(array|Collection|null $values): array
    {
        $normalized = collect($values ?? [])
            ->map(function ($value) {
                if (! is_string($value)) {
                    return $value;
                }

                $trim = trim($value);
                if (($trim === '' && $trim !== '0') || (! $trim)) {
                    return $trim;
                }

                if (in_array($trim[0], ['{', '['], true)) {
                    $decoded = json_decode($trim, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $decoded;
                    }
                }

                return $value;
            })
            ->toArray();

        return Arr::undot($normalized);
    }

    protected function mergeStructuredValues(array $existing, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            if (
                array_key_exists($key, $existing)
                && is_array($existing[$key])
                && is_array($value)
                && Arr::isAssoc($existing[$key])
                && Arr::isAssoc($value)
            ) {
                $existing[$key] = $this->mergeStructuredValues($existing[$key], $value);

                continue;
            }

            $existing[$key] = $value;
        }

        return $existing;
    }

    protected function applyAuthoritativePricing(
        Order $order,
        array $details,
        array $metadata,
        string $product,
        int $fallbackCustomerPriceCents
    ): array {
        $pricingPayload = $this->orderPricingPayloadService->payloadFromDetails($details, $product);
        $discountAmount = (float) ($order->discount_amount ?? 0);
        $payoutPercentage = $this->orderPricingPayloadService->payoutPercentage($order);

        if (! $this->orderPricingPayloadService->canAuthoritativelyPrice($pricingPayload)) {
            $financials = $this->orderFinancialsService->fromCustomerPriceCents(
                $fallbackCustomerPriceCents,
                $discountAmount,
                $payoutPercentage,
            );

            $metadata['pricing'] = array_merge((array) ($metadata['pricing'] ?? []), [
                'subtotal' => round($financials['original_price_cents'] / 100, 2),
                'originalTotal' => round($financials['original_price_cents'] / 100, 2),
                'discountAmount' => round($financials['discount_amount'], 2),
                'finalTotal' => round($financials['price_cents'] / 100, 2),
                'boosterPayoutBasis' => round($financials['booster_payout_basis_cents'] / 100, 2),
            ]);

            return [$details, $metadata, $financials];
        }

        $pricedPayload = $this->orderPricingPayloadService->calculate($pricingPayload);
        $originalPriceCents = (int) round(((float) data_get($pricedPayload, 'pricing.total', 0)) * 100);
        $financials = $this->orderFinancialsService->fromOriginalPriceCents(
            $originalPriceCents,
            $discountAmount,
            $payoutPercentage,
        );
        $details = BoostingCatalog::sanitizeOrderDetails(
            $this->orderPricingPayloadService->syncDetails($details, $pricedPayload, $product)
        );
        $metadata['pricing'] = array_merge((array) ($metadata['pricing'] ?? []), [
            'subtotal' => round($financials['original_price_cents'] / 100, 2),
            'originalTotal' => round($financials['original_price_cents'] / 100, 2),
            'discountAmount' => round($financials['discount_amount'], 2),
            'finalTotal' => round($financials['price_cents'] / 100, 2),
            'boosterPayoutBasis' => round($financials['booster_payout_basis_cents'] / 100, 2),
        ]);

        return [$details, $metadata, $financials];
    }

    protected function applyAdminOverridePricing(
        Order $order,
        array $details,
        array $metadata,
        string $product,
        int $customerPriceCents,
        string $currency
    ): array {
        $preview = $this->authoritativePricingPreview($details, $product);
        $previewTotal = $preview['priceCents'] !== null ? round($preview['priceCents'] / 100, 2) : null;
        $previewErrors = $preview['validationErrors'] !== [] ? $preview['validationErrors'] : null;
        $financials = $this->orderFinancialsService->fromCustomerPriceCents(
            $customerPriceCents,
            (float) ($order->discount_amount ?? 0),
            $this->orderPricingPayloadService->payoutPercentage($order),
        );
        $existingBypassed = (bool) (data_get($metadata, 'adminOverride.customerRestrictionsBypassed')
            ?? data_get($details, 'adminOverride.customerRestrictionsBypassed')
            ?? false);
        $customerRestrictionsBypassed = $existingBypassed || ($preview['available'] && ! $preview['valid']);
        $details['adminOverride'] = array_merge((array) ($details['adminOverride'] ?? []), [
            'enabled' => true,
            'customerRestrictionsBypassed' => $customerRestrictionsBypassed,
            'manualPriceApplied' => true,
        ]);
        $orderPayload = is_array($details['order'] ?? null) ? $details['order'] : [];
        $orderPayload['pricing'] = array_merge((array) ($orderPayload['pricing'] ?? []), array_filter([
            'source' => 'admin-manual-override',
            'currency' => $currency,
            'subtotal' => round($financials['original_price_cents'] / 100, 2),
            'originalTotal' => round($financials['original_price_cents'] / 100, 2),
            'discountAmount' => round($financials['discount_amount'], 2),
            'finalPrice' => round($financials['price_cents'] / 100, 2),
            'total' => round($financials['price_cents'] / 100, 2),
            'boosterPayoutBasis' => round($financials['booster_payout_basis_cents'] / 100, 2),
            'authoritativePreviewAvailable' => $preview['available'],
            'authoritativePreviewValid' => $preview['valid'],
            'authoritativePreviewTotal' => $previewTotal,
            'authoritativePreviewValidationErrors' => $previewErrors,
        ], fn ($value) => $value !== null && $value !== []));
        $details['order'] = $orderPayload;
        $metadata['source'] = 'admin-custom-order';
        $metadata['pricing'] = array_merge((array) ($metadata['pricing'] ?? []), array_filter([
            'source' => 'admin-manual-override',
            'subtotal' => round($financials['original_price_cents'] / 100, 2),
            'originalTotal' => round($financials['original_price_cents'] / 100, 2),
            'discountAmount' => round($financials['discount_amount'], 2),
            'finalTotal' => round($financials['price_cents'] / 100, 2),
            'boosterPayoutBasis' => round($financials['booster_payout_basis_cents'] / 100, 2),
            'authoritativePreviewAvailable' => $preview['available'],
            'authoritativePreviewValid' => $preview['valid'],
            'authoritativePreviewTotal' => $previewTotal,
            'authoritativePreviewValidationErrors' => $previewErrors,
        ], fn ($value) => $value !== null && $value !== []));
        $metadata['adminOverride'] = array_merge((array) ($metadata['adminOverride'] ?? []), array_filter([
            'enabled' => true,
            'customerRestrictionsBypassed' => $customerRestrictionsBypassed,
            'pricingMode' => 'manual-price',
            'manualPriceApplied' => true,
            'manualPriceCents' => $financials['price_cents'],
            'manualPrice' => round($financials['price_cents'] / 100, 2),
            'authoritativePreviewAvailable' => $preview['available'],
            'authoritativePreviewValid' => $preview['valid'],
            'authoritativePreviewTotal' => $previewTotal,
            'authoritativePreviewValidationErrors' => $previewErrors,
        ], fn ($value) => $value !== null && $value !== []));

        return [$details, $metadata, $financials];
    }

    protected function authoritativePricingPreview(array $details, string $product): array
    {
        $pricingPayload = $this->orderPricingPayloadService->payloadFromDetails($details, $product);
        $preview = [
            'available' => false,
            'valid' => false,
            'priceCents' => null,
            'validationErrors' => [],
        ];

        if (! $this->orderPricingPayloadService->canAuthoritativelyPrice($pricingPayload)) {
            return $preview;
        }

        $preview['available'] = true;

        try {
            $pricedPayload = $this->orderPricingPayloadService->calculate($pricingPayload);
            $preview['valid'] = true;
            $preview['priceCents'] = (int) round(((float) data_get($pricedPayload, 'pricing.total', 0)) * 100);
        } catch (ValidationException $exception) {
            $preview['validationErrors'] = $exception->errors();
        }

        return $preview;
    }

    protected function normalizeAdminOverrideDetails(array $details, string $product): array
    {
        $manualPayload = AdminManualOrderData::payloadFromDetails($details, $product);

        return AdminManualOrderData::syncDetails($details, $manualPayload, [
            'source' => 'admin-custom-order',
            'orderCategory' => 'manual',
            'adminOverride' => [
                'enabled' => true,
            ],
        ]);
    }

    protected function syncProductDetails(array $details, string $product): array
    {
        $service = trim($product);

        if ($service === '') {
            return $details;
        }

        $details['service'] = $service;

        $orderPayload = $details['order'] ?? [];
        $orderPayload = is_array($orderPayload) ? $orderPayload : [];
        $orderPayload['orderType'] = $service;
        $details['order'] = BoostingCatalog::sanitizeOrderPayload($orderPayload);

        return $details;
    }

    protected function isAdminOverrideOrder(Order $order, array $metadata): bool
    {
        return (bool) ($order->is_custom || data_get($metadata, 'adminOverride.enabled'));
    }

    protected function withStatusTransitionMetadata(Order $order, array $metadata, ?string $previousStatus, string $nextStatus, array $data): array
    {
        if ($previousStatus === $nextStatus) {
            return $metadata;
        }

        $event = OrderLifecycleMetadata::eventKey($previousStatus, $nextStatus);

        if ($event === null) {
            return $metadata;
        }

        return OrderLifecycleMetadata::record($metadata, $event, $previousStatus, $nextStatus, [
            'source' => 'admin',
            'reason' => $data['status_reason'] ?? null,
            'next_step' => $this->statusNextStep($event),
            'refund' => $event === 'refunded' ? $this->refundMetadata($order, $data) : null,
            'completion' => $event === 'completed' ? [
                'proof_uploaded_at' => $order->completion_proof_uploaded_at?->toIso8601String(),
                'proof_available' => is_string($order->completion_proof_path) && trim($order->completion_proof_path) !== '',
            ] : null,
        ]);
    }

    protected function refundMetadata(Order $order, array $data): array
    {
        $metadata = is_array($order->metadata) ? $order->metadata : [];
        $method = trim((string) ($data['refund_method'] ?? ''));
        $reference = trim((string) ($data['refund_reference'] ?? ''));
        $arrival = trim((string) ($data['refund_arrival_estimate'] ?? ''));
        $amountCents = array_key_exists('refund_amount', $data) && $data['refund_amount'] !== null && $data['refund_amount'] !== ''
            ? (int) round(((float) $data['refund_amount']) * 100)
            : $order->customerPriceCents();

        return [
            'amount_cents' => max(0, $amountCents),
            'method' => $method !== '' ? $method : $this->paymentProviderLabel((string) data_get($metadata, 'paymentProvider', data_get($metadata, 'paymentMethod', ''))),
            'destination' => 'Original payment method',
            'estimated_arrival' => $arrival !== '' ? $arrival : 'Usually 5-10 business days; provider and bank timing can vary.',
            'reference' => $reference !== '' ? $reference : null,
        ];
    }

    protected function paymentProviderLabel(string $provider): string
    {
        $provider = trim($provider);

        return $provider !== '' ? Str::headline($provider) : 'Original payment method';
    }

    protected function statusNextStep(string $event): ?string
    {
        return match ($event) {
            'paused' => 'We will resume the order once the hold is cleared. Watch the order dashboard for updates.',
            'resumed', 'assigned' => 'Work continues from the order dashboard.',
            'completed' => 'Review the final order in your dashboard and contact support if anything looks wrong.',
            'cancelled' => 'Review the order dashboard or contact support if you need clarification.',
            'refunded' => 'Watch your original payment method for the refund. Provider and bank timing can vary.',
            default => null,
        };
    }
}
