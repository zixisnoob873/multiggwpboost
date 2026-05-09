<?php

namespace App\Services\Orders;

use App\Data\Payments\PaymentCheckoutData;
use App\Data\Payments\PendingCheckout;
use App\Models\Order;
use App\Models\OrderExtension;
use App\Models\OrderTip;
use App\Models\User;
use App\Services\Chat\SendOrderSystemMessage;
use App\Support\OrderLifecycleMetadata;
use App\Support\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RankTrackerActionService
{
    public function __construct(
        protected OrderPricingPayloadService $payloadService,
        protected OrderFinancialsService $orderFinancialsService,
        protected SendOrderSystemMessage $sendOrderSystemMessage,
    ) {}

    public function actionState(User $customer, Order $order, bool $paymentsAvailable): array
    {
        $this->assertOwnedByCustomer($customer, $order);

        $extensionModal = $this->extensionModal($order);
        $isPaid = (string) ($order->payment_status ?? 'pending') === 'paid';
        $isClosed = $order->isClosedStatus();

        return [
            'canExtend' => $paymentsAvailable && $isPaid && ! $isClosed && ($extensionModal['canSubmit'] ?? false),
            'canPauseToggle' => in_array($order->status, [OrderStatus::IN_PROGRESS, OrderStatus::PAUSED], true),
            'pauseLabel' => $order->status === OrderStatus::PAUSED ? 'Continue Boost' : 'Pause Boost',
            'pauseTargetStatus' => $order->status === OrderStatus::PAUSED ? OrderStatus::IN_PROGRESS : OrderStatus::PAUSED,
            'canTipBooster' => $paymentsAvailable && $isPaid && ! $isClosed && $order->booster_id !== null,
            'canTipAdmin' => $paymentsAvailable && $isPaid && ! $isClosed,
            'paymentsAvailable' => $paymentsAvailable,
        ];
    }

    public function extensionModal(Order $order): array
    {
        try {
            $pricedPayload = $this->payloadService->calculateForOrder($order);
        } catch (\Throwable) {
            return [
                'serviceType' => $this->payloadService->serviceType($order),
                'title' => 'Extend Boost',
                'description' => 'Extension options are unavailable until this order has a complete pricing payload.',
                'summaryLabel' => 'Current service',
                'summaryValue' => $this->payloadService->serviceType($order),
                'field' => null,
                'canSubmit' => false,
            ];
        }

        $serviceType = (string) ($pricedPayload['orderType'] ?? $pricedPayload['serviceType'] ?? $this->payloadService->serviceType($order));

        return match ($serviceType) {
            'Ranked Wins' => [
                'serviceType' => $serviceType,
                'title' => 'Extend Ranked Wins',
                'description' => 'Add more ranked wins to the same live order without opening a separate workspace.',
                'summaryLabel' => 'Current win target',
                'summaryValue' => sprintf('%s wins', (int) ($pricedPayload['numberOfWins'] ?? 0)),
                'field' => [
                    'type' => 'number',
                    'name' => 'additional_wins',
                    'label' => 'Additional wins',
                    'min' => 1,
                    'max' => 25,
                    'step' => 1,
                    'value' => 1,
                    'help' => 'Only extra ranked wins are added. Existing progress and addons stay on the order.',
                ],
                'canSubmit' => true,
            ],
            'Placement Matches' => [
                'serviceType' => $serviceType,
                'title' => 'Extend Placement Matches',
                'description' => 'Increase the placement-match count on this order while keeping the same queue preferences.',
                'summaryLabel' => 'Current placement count',
                'summaryValue' => sprintf('%s matches', (int) ($pricedPayload['numberOfPlacementGames'] ?? 0)),
                'field' => [
                    'type' => 'number',
                    'name' => 'additional_placement_games',
                    'label' => 'Additional placement matches',
                    'min' => 1,
                    'max' => 5,
                    'step' => 1,
                    'value' => 1,
                    'help' => 'Placement extensions stay capped to a compact add-on range per purchase.',
                ],
                'canSubmit' => true,
            ],
            'Radiant Boost' => $this->radiantExtensionModal($pricedPayload),
            default => $this->rankBoostExtensionModal($pricedPayload),
        };
    }

    public function buildExtensionCheckout(User $customer, Order $order, array $data): array
    {
        $this->assertOwnedByCustomer($customer, $order);
        $this->assertPaidActiveOrder($order);

        $currentPayload = $this->payloadService->calculateForOrder($order);
        $serviceType = (string) ($currentPayload['orderType'] ?? $currentPayload['serviceType'] ?? $this->payloadService->serviceType($order));
        $updatedInput = $this->payloadService->basePayload($order);
        $selectionPayload = [];

        match ($serviceType) {
            'Ranked Wins' => $this->applyRankedWinsExtension($updatedInput, $selectionPayload, $currentPayload, $data),
            'Placement Matches' => $this->applyPlacementExtension($updatedInput, $selectionPayload, $currentPayload, $data),
            'Radiant Boost' => $this->applyRadiantExtension($updatedInput, $selectionPayload, $currentPayload, $data),
            default => $this->applyRankBoostExtension($updatedInput, $selectionPayload, $currentPayload, $data),
        };

        $updatedPayload = $this->payloadService->calculate($updatedInput);
        $currentCustomerTotalCents = $order->customerPriceCents();
        $currentOriginalTotalCents = $order->resolvedOriginalPriceCents();
        $newOriginalTotalCents = (int) round(((float) data_get($updatedPayload, 'pricing.total', 0)) * 100);
        $financials = $this->orderFinancialsService->fromOriginalPriceCents(
            $newOriginalTotalCents,
            (float) ($order->discount_amount ?? 0),
            $this->payloadService->payoutPercentage($order),
        );

        if ($newOriginalTotalCents <= $currentOriginalTotalCents) {
            throw ValidationException::withMessages([
                'extension' => ['Choose a larger extension before continuing to payment.'],
            ]);
        }

        $amountCents = (int) $financials['price_cents'] - $currentCustomerTotalCents;

        if ($amountCents <= 0) {
            throw ValidationException::withMessages([
                'extension' => ['This extension does not increase the payable total. Please contact support if pricing looks incorrect.'],
            ]);
        }

        return [
            'checkoutData' => new PaymentCheckoutData(
                requestData: $this->payloadService->contactData($order, $customer),
                orderPayload: $updatedPayload,
                paymentMethod: (string) $data['paymentMethod'],
                priceCents: $amountCents,
                total: round($amountCents / 100, 2),
                subtotal: round($amountCents / 100, 2),
            ),
            'metadata' => [
                'checkoutKind' => 'order_extension',
                'orderId' => $order->id,
                'serviceType' => $serviceType,
                'selectionPayload' => $selectionPayload,
                'previousOrderPayload' => $currentPayload,
                'resultingTotalCents' => $financials['price_cents'],
                'resultingOriginalTotalCents' => $financials['original_price_cents'],
                'resultingBoosterPayoutBasisCents' => $financials['booster_payout_basis_cents'],
                'resultingBoosterPayoutCents' => $financials['booster_payout_cents'],
                'successMessage' => 'Boost extension purchased successfully.',
            ],
        ];
    }

    public function buildTipCheckout(User $customer, Order $order, array $data, string $recipientType): array
    {
        $this->assertOwnedByCustomer($customer, $order);
        $this->assertPaidActiveOrder($order);

        if ($recipientType === OrderTip::RECIPIENT_BOOSTER && ! $order->booster_id) {
            throw ValidationException::withMessages([
                'amount' => ['You can only tip a booster after the order has been assigned.'],
            ]);
        }

        $amount = (float) ($data['amount'] ?? 0);
        $amountCents = (int) round($amount * 100);

        if ($amountCents < 100) {
            throw ValidationException::withMessages([
                'amount' => ['Enter at least $1.00.'],
            ]);
        }

        if ($amountCents > 100000) {
            throw ValidationException::withMessages([
                'amount' => ['Keep the tip at $1,000.00 or below.'],
            ]);
        }

        $label = $recipientType === OrderTip::RECIPIENT_BOOSTER ? 'Booster Tip' : 'Admin Tip';

        return [
            'checkoutData' => new PaymentCheckoutData(
                requestData: $this->payloadService->contactData($order, $customer),
                orderPayload: [
                    'orderType' => $label,
                    'serviceType' => $label,
                ],
                paymentMethod: (string) $data['paymentMethod'],
                priceCents: $amountCents,
                total: round($amountCents / 100, 2),
                subtotal: round($amountCents / 100, 2),
            ),
            'metadata' => [
                'checkoutKind' => $recipientType === OrderTip::RECIPIENT_BOOSTER ? 'order_tip_booster' : 'order_tip_admin',
                'orderId' => $order->id,
                'recipientType' => $recipientType,
                'successMessage' => $recipientType === OrderTip::RECIPIENT_BOOSTER
                    ? 'Tip sent to the booster successfully.'
                    : 'Tip sent to admin successfully.',
            ],
        ];
    }

    public function pause(User $customer, Order $order): Order
    {
        return DB::transaction(function () use ($customer, $order) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $this->assertOwnedByCustomer($customer, $lockedOrder);

            if ($lockedOrder->status !== OrderStatus::IN_PROGRESS) {
                throw new HttpException(422, 'Only in-progress orders can be paused.');
            }

            $metadata = is_array($lockedOrder->metadata) ? $lockedOrder->metadata : [];
            $metadata = OrderLifecycleMetadata::record($metadata, 'paused', OrderStatus::IN_PROGRESS, OrderStatus::PAUSED, [
                'source' => 'customer',
                'actor_id' => $customer->getKey(),
                'reason' => 'Paused from customer dashboard.',
                'customer_action_required' => true,
                'next_step' => 'Resume the order from your dashboard when you are ready.',
            ]);

            $lockedOrder->forceFill([
                'status' => OrderStatus::PAUSED,
                'metadata' => $metadata,
            ])->save();

            return $lockedOrder->refresh();
        }, 3);
    }

    public function resume(User $customer, Order $order): Order
    {
        return DB::transaction(function () use ($customer, $order) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $this->assertOwnedByCustomer($customer, $lockedOrder);

            if ($lockedOrder->status !== OrderStatus::PAUSED) {
                throw new HttpException(422, 'Only paused orders can be continued.');
            }

            $metadata = is_array($lockedOrder->metadata) ? $lockedOrder->metadata : [];
            $metadata = OrderLifecycleMetadata::record($metadata, 'resumed', OrderStatus::PAUSED, OrderStatus::IN_PROGRESS, [
                'source' => 'customer',
                'actor_id' => $customer->getKey(),
                'reason' => 'Resumed from customer dashboard.',
                'customer_action_required' => false,
                'next_step' => 'Work continues from the order dashboard.',
            ]);

            $lockedOrder->forceFill([
                'status' => OrderStatus::IN_PROGRESS,
                'metadata' => $metadata,
            ])->save();

            return $lockedOrder->refresh();
        }, 3);
    }

    public function fulfillExtension(PendingCheckout $pendingCheckout, string $providerKey, array $paymentAttributes): Order
    {
        return DB::transaction(function () use ($pendingCheckout, $providerKey, $paymentAttributes) {
            $orderId = (int) ($pendingCheckout->metadata['orderId'] ?? 0);
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);

            $existing = OrderExtension::query()
                ->where('checkout_reference', $pendingCheckout->reference)
                ->first();

            if ($existing) {
                return $order->refresh();
            }

            $updatedPayload = is_array($pendingCheckout->orderPayload) ? $pendingCheckout->orderPayload : [];
            $previousPayload = (array) ($pendingCheckout->metadata['previousOrderPayload'] ?? $this->payloadService->calculateForOrder($order));
            $newOriginalTotalCents = (int) ($pendingCheckout->metadata['resultingOriginalTotalCents'] ?? round(((float) data_get($updatedPayload, 'pricing.total', 0)) * 100));
            $financials = $this->orderFinancialsService->fromOriginalPriceCents(
                $newOriginalTotalCents,
                (float) ($order->discount_amount ?? 0),
                $this->payloadService->payoutPercentage($order),
            );
            $previousTotalCents = $order->customerPriceCents();
            $previousOriginalTotalCents = $order->resolvedOriginalPriceCents();
            $previousBoosterPayoutCents = $order->resolvedBoosterPayoutCents();
            $newBoosterPayoutCents = (int) ($pendingCheckout->metadata['resultingBoosterPayoutCents']
                ?? $financials['booster_payout_cents']);

            $metadata = is_array($order->metadata) ? $order->metadata : (json_decode((string) $order->metadata, true) ?: []);
            $details = $this->payloadService->syncOrderDetails($order, $updatedPayload);

            $metadata['pricing'] = array_merge((array) ($metadata['pricing'] ?? []), [
                'subtotal' => round($financials['original_price_cents'] / 100, 2),
                'originalTotal' => round($financials['original_price_cents'] / 100, 2),
                'discountAmount' => round($financials['discount_amount'], 2),
                'finalTotal' => round($financials['price_cents'] / 100, 2),
                'boosterPayoutBasis' => round($financials['booster_payout_basis_cents'] / 100, 2),
                'extensionsTotalCents' => (int) (($metadata['pricing']['extensionsTotalCents'] ?? 0) + $pendingCheckout->priceCents),
            ]);
            $metadata['latestExtension'] = [
                'message' => 'Boost order has been extended, please re-read the order details before continuing',
                'changedAt' => now()->toIso8601String(),
                'deltaCents' => (int) $pendingCheckout->priceCents,
                'newTotalCents' => $financials['price_cents'],
                'newOriginalTotalCents' => $financials['original_price_cents'],
                'newBoosterPayoutBasisCents' => $financials['booster_payout_basis_cents'],
                'newBoosterPayoutCents' => $newBoosterPayoutCents,
            ];

            $order->forceFill([
                'price_cents' => $financials['price_cents'],
                'original_price_cents' => $financials['original_price_cents'],
                'discount_amount' => $financials['discount_amount'],
                'booster_payout_rate' => $financials['booster_payout_rate'],
                'booster_payout_basis_cents' => $financials['booster_payout_basis_cents'],
                'booster_payout_cents' => $newBoosterPayoutCents,
                'details' => $details,
                'metadata' => $metadata,
            ])->save();

            OrderExtension::query()->create([
                'order_id' => $order->id,
                'customer_id' => $order->user_id,
                'service_type' => (string) ($pendingCheckout->metadata['serviceType'] ?? $this->payloadService->serviceType($order)),
                'checkout_reference' => $pendingCheckout->reference,
                'amount_cents' => (int) $pendingCheckout->priceCents,
                'previous_total_cents' => $previousTotalCents,
                'new_total_cents' => $financials['price_cents'],
                'previous_booster_payout_cents' => $previousBoosterPayoutCents,
                'new_booster_payout_cents' => $newBoosterPayoutCents,
                'selection_payload' => (array) ($pendingCheckout->metadata['selectionPayload'] ?? []),
                'previous_order_payload' => $previousPayload,
                'updated_order_payload' => $updatedPayload,
                'payment_provider' => $providerKey,
                'payment_reference' => $paymentAttributes['payment_reference'] ?? null,
                'stripe_session_id' => $paymentAttributes['stripe_session_id'] ?? null,
                'paid_at' => $paymentAttributes['paid_at'] ?? now(),
                'metadata' => [
                    'checkoutKind' => 'order_extension',
                    'previousOriginalTotalCents' => $previousOriginalTotalCents,
                    'newOriginalTotalCents' => $financials['original_price_cents'],
                    'boosterPayoutBasisCents' => $financials['booster_payout_basis_cents'],
                ],
            ]);

            $this->sendOrderSystemMessage->execute(
                $order,
                \App\Enums\OrderChatThreadType::CUSTOMER_ADMIN,
                'Boost has been successfully extended, Your booster has been notified. Just to be safe, please inform your booster in chat about the extension'
            );

            $this->sendOrderSystemMessage->execute(
                $order,
                \App\Enums\OrderChatThreadType::BOOSTER_ADMIN,
                'Boost order has been extended, please re-read the order details before continuing'
            );

            $this->sendOrderSystemMessage->execute(
                $order,
                \App\Enums\OrderChatThreadType::CUSTOMER_BOOSTER,
                'Boost has been extended'
            );

            return $order->refresh();
        }, 3);
    }

    public function fulfillTip(PendingCheckout $pendingCheckout, string $providerKey, array $paymentAttributes): Order
    {
        return DB::transaction(function () use ($pendingCheckout, $providerKey, $paymentAttributes) {
            $orderId = (int) ($pendingCheckout->metadata['orderId'] ?? 0);
            $recipientType = (string) ($pendingCheckout->metadata['recipientType'] ?? OrderTip::RECIPIENT_ADMIN);
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);

            $existing = OrderTip::query()
                ->where('checkout_reference', $pendingCheckout->reference)
                ->first();

            if ($existing) {
                return $order->refresh();
            }

            OrderTip::query()->create([
                'order_id' => $order->id,
                'customer_id' => $order->user_id,
                'booster_id' => $recipientType === OrderTip::RECIPIENT_BOOSTER ? $order->booster_id : null,
                'recipient_type' => $recipientType,
                'checkout_reference' => $pendingCheckout->reference,
                'amount_cents' => (int) $pendingCheckout->priceCents,
                'payment_provider' => $providerKey,
                'payment_reference' => $paymentAttributes['payment_reference'] ?? null,
                'stripe_session_id' => $paymentAttributes['stripe_session_id'] ?? null,
                'paid_at' => $paymentAttributes['paid_at'] ?? now(),
                'metadata' => [
                    'checkoutKind' => $pendingCheckout->metadata['checkoutKind'] ?? null,
                ],
            ]);

            return $order->refresh();
        }, 3);
    }

    protected function rankBoostExtensionModal(array $pricedPayload): array
    {
        $currentTarget = (string) ($pricedPayload['desiredDivision'] ?? 'Unranked');
        $options = $this->payloadService->higherRanks($currentTarget);

        return [
            'serviceType' => 'Rank Boosting',
            'title' => 'Extend Rank Boost',
            'description' => 'Raise the final target rank on this order while keeping the same queue settings and addons.',
            'summaryLabel' => 'Current target',
            'summaryValue' => $currentTarget,
            'field' => [
                'type' => 'select',
                'name' => 'target_division',
                'label' => 'New target rank',
                'options' => $options,
                'value' => $options[0] ?? $currentTarget,
                'help' => 'Only higher targets are offered so the extension always expands the original order.',
            ],
            'canSubmit' => count($options) > 0,
        ];
    }

    protected function radiantExtensionModal(array $pricedPayload): array
    {
        $currentRank = (string) ($pricedPayload['currentDivision'] ?? 'Unranked');
        $options = $this->payloadService->lowerRanks($currentRank);

        return [
            'serviceType' => 'Radiant Boost',
            'title' => 'Extend Radiant Boost',
            'description' => 'Recalculate the same Radiant push from a lower current rank when the run needs more scope.',
            'summaryLabel' => 'Current start rank',
            'summaryValue' => $currentRank,
            'field' => [
                'type' => 'select',
                'name' => 'current_division',
                'label' => 'Updated current rank',
                'options' => $options,
                'value' => $options[0] ?? $currentRank,
                'help' => 'Radiant remains locked as the target. The extension only broadens the starting point.',
            ],
            'canSubmit' => count($options) > 0,
        ];
    }

    protected function applyRankBoostExtension(array &$updatedInput, array &$selectionPayload, array $currentPayload, array $data): void
    {
        $targetDivision = trim((string) ($data['target_division'] ?? ''));
        if ($targetDivision === '') {
            throw ValidationException::withMessages([
                'target_division' => ['Choose a new target rank.'],
            ]);
        }

        $allowed = $this->payloadService->higherRanks((string) ($currentPayload['desiredDivision'] ?? ''));
        if (! in_array($targetDivision, $allowed, true)) {
            throw ValidationException::withMessages([
                'target_division' => ['Choose a higher target rank for this extension.'],
            ]);
        }

        $updatedInput['targetDivision'] = $targetDivision;
        $selectionPayload = [
            'targetDivision' => $targetDivision,
        ];
    }

    protected function applyRankedWinsExtension(array &$updatedInput, array &$selectionPayload, array $currentPayload, array $data): void
    {
        $additionalWins = (int) ($data['additional_wins'] ?? 0);
        if ($additionalWins < 1 || $additionalWins > 25) {
            throw ValidationException::withMessages([
                'additional_wins' => ['Choose between 1 and 25 additional wins.'],
            ]);
        }

        $updatedInput['numberOfWins'] = (int) ($currentPayload['numberOfWins'] ?? 0) + $additionalWins;
        $selectionPayload = [
            'additionalWins' => $additionalWins,
        ];
    }

    protected function applyPlacementExtension(array &$updatedInput, array &$selectionPayload, array $currentPayload, array $data): void
    {
        $additionalMatches = (int) ($data['additional_placement_games'] ?? 0);
        if ($additionalMatches < 1 || $additionalMatches > 5) {
            throw ValidationException::withMessages([
                'additional_placement_games' => ['Choose between 1 and 5 additional placement matches.'],
            ]);
        }

        $updatedInput['numberOfPlacementGames'] = (int) ($currentPayload['numberOfPlacementGames'] ?? 0) + $additionalMatches;
        $selectionPayload = [
            'additionalPlacementGames' => $additionalMatches,
        ];
    }

    protected function applyRadiantExtension(array &$updatedInput, array &$selectionPayload, array $currentPayload, array $data): void
    {
        $currentDivision = trim((string) ($data['current_division'] ?? ''));
        if ($currentDivision === '') {
            throw ValidationException::withMessages([
                'current_division' => ['Choose an updated current rank.'],
            ]);
        }

        $allowed = $this->payloadService->lowerRanks((string) ($currentPayload['currentDivision'] ?? ''));
        if (! in_array($currentDivision, $allowed, true)) {
            throw ValidationException::withMessages([
                'current_division' => ['Choose a lower current rank to extend this Radiant order.'],
            ]);
        }

        $updatedInput['currentDivision'] = $currentDivision;
        $selectionPayload = [
            'currentDivision' => $currentDivision,
        ];
    }

    protected function assertOwnedByCustomer(User $customer, Order $order): void
    {
        if ($customer->role !== 'customer' || (int) $order->user_id !== (int) $customer->id) {
            throw new HttpException(403, 'You are not allowed to manage this order.');
        }
    }

    protected function assertPaidActiveOrder(Order $order): void
    {
        if ((string) ($order->payment_status ?? 'pending') !== 'paid') {
            throw ValidationException::withMessages([
                'payment' => ['Only paid orders can be changed from the Rank Tracker.'],
            ]);
        }

        if ($order->isClosedStatus()) {
            throw ValidationException::withMessages([
                'order' => ['Completed and cancelled orders can no longer be changed here.'],
            ]);
        }
    }
}
