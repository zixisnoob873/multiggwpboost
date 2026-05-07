<?php

namespace App\Services\Payments;

use App\Data\Payments\PendingCheckout;
use App\Models\Order;
use App\Support\Logging\AppEventLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinalizePendingCheckoutService
{
    public function __construct(
        protected PendingCheckoutStore $pendingCheckoutStore,
        protected PendingCheckoutFulfillmentService $pendingCheckoutFulfillmentService,
        protected AppEventLogger $eventLogger,
    ) {}

    public function finalize(PendingCheckout $pendingCheckout, string $providerKey, array $verificationUpdates = []): Order
    {
        $paymentAttributes = $this->paymentAttributes($pendingCheckout, $providerKey, $verificationUpdates);

        try {
            $order = DB::transaction(function () use ($pendingCheckout, $providerKey, $paymentAttributes) {
                $existingOrder = $this->findExistingOrder($pendingCheckout, $paymentAttributes, true);

                if ($existingOrder) {
                    return $existingOrder;
                }

                return $this->pendingCheckoutFulfillmentService->fulfill(
                    $pendingCheckout,
                    $providerKey,
                    $paymentAttributes,
                );
            }, 3);
        } catch (ValidationException|UniqueConstraintViolationException $exception) {
            $existingOrder = $this->findExistingOrder($pendingCheckout, $paymentAttributes);

            if (! $existingOrder) {
                throw $exception;
            }

            $order = $existingOrder;
        }

        $this->markCheckoutCompleted($pendingCheckout, $order);
        $this->eventLogger->payment('payment.completed', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'provider' => $providerKey,
            'checkout_reference' => $pendingCheckout->reference,
            'checkout_kind' => (string) ($pendingCheckout->metadata['checkoutKind'] ?? 'default'),
            'payment_reference' => $paymentAttributes['payment_reference'] ?? null,
            'stripe_session_id' => $paymentAttributes['stripe_session_id'] ?? null,
            'price_cents' => $order->price_cents,
        ]);

        return $order;
    }

    public function finalizeFree(PendingCheckout $pendingCheckout): Order
    {
        return $this->finalize($pendingCheckout, 'free', [
            'payment_reference' => $this->freePaymentReference($pendingCheckout),
            'metadata' => [
                'isFreeCheckout' => true,
            ],
        ]);
    }

    protected function markCheckoutCompleted(PendingCheckout $pendingCheckout, Order $order): void
    {
        $this->pendingCheckoutStore->markCompleted(
            $pendingCheckout->with([
                'completedOrderId' => $order->id,
                'metadata' => array_merge($pendingCheckout->metadata, [
                    'completedOrderId' => $order->id,
                ]),
            ]),
            $order
        );
    }

    protected function paymentAttributes(PendingCheckout $pendingCheckout, string $providerKey, array $verificationUpdates): array
    {
        return [
            ...$verificationUpdates,
            'payment_status' => $verificationUpdates['payment_status'] ?? 'paid',
            'paid_at' => $verificationUpdates['paid_at'] ?? now(),
            'metadata' => array_merge(
                $pendingCheckout->metadata,
                (array) ($verificationUpdates['metadata'] ?? []),
                [
                    'paymentProvider' => $providerKey,
                    'paymentMethod' => $providerKey,
                    'checkoutReference' => $pendingCheckout->reference,
                ]
            ),
        ];
    }

    protected function findExistingOrder(PendingCheckout $pendingCheckout, array $paymentAttributes, bool $lockForUpdate = false): ?Order
    {
        $completedOrderId = $pendingCheckout->completedOrderId ?? $pendingCheckout->metadata['completedOrderId'] ?? null;
        if ($completedOrderId) {
            $existingOrder = $this->orderQuery($lockForUpdate)
                ->whereKey($completedOrderId)
                ->first();

            if ($existingOrder) {
                return $existingOrder;
            }
        }

        $stripeSessionId = $paymentAttributes['stripe_session_id'] ?? $pendingCheckout->metadata['stripeSessionId'] ?? null;
        $paymentReference = $paymentAttributes['payment_reference'] ?? null;

        if (! $stripeSessionId && ! $paymentReference) {
            return null;
        }

        return $this->orderQuery($lockForUpdate)
            ->where(function (Builder $query) use ($stripeSessionId, $paymentReference) {
                if ($stripeSessionId) {
                    $query->orWhere('stripe_session_id', $stripeSessionId);
                }

                if ($paymentReference) {
                    $query->orWhere('payment_reference', $paymentReference);
                }
            })
            ->first();
    }

    protected function orderQuery(bool $lockForUpdate = false): Builder
    {
        $query = Order::query();

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query;
    }

    protected function freePaymentReference(PendingCheckout $pendingCheckout): string
    {
        return 'free:'.$pendingCheckout->reference;
    }
}
