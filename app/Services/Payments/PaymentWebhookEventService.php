<?php

namespace App\Services\Payments;

use App\Models\PaymentWebhookEvent;

class PaymentWebhookEventService
{
    public function begin(string $provider, string $eventId, ?string $eventType, array $payload = [], ?int $pendingCheckoutId = null): PaymentWebhookEventResult
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($provider, $eventId, $eventType, $payload, $pendingCheckoutId) {
            $event = PaymentWebhookEvent::query()
                ->where('provider', $provider)
                ->where('event_id', $eventId)
                ->lockForUpdate()
                ->first();

            if ($event && $event->isTerminal()) {
                return new PaymentWebhookEventResult($event, 'already_handled');
            }

            if ($event && $event->status === PaymentWebhookEvent::STATUS_PROCESSING && $event->updated_at && $event->updated_at->gt(now()->subMinutes($this->processingTimeoutMinutes()))) {
                return new PaymentWebhookEventResult($event, 'already_processing');
            }

            $event ??= new PaymentWebhookEvent([
                'provider' => $provider,
                'event_id' => $eventId,
            ]);

            $event->forceFill([
                'event_type' => $eventType,
                'status' => PaymentWebhookEvent::STATUS_PROCESSING,
                'attempts' => ((int) $event->attempts) + 1,
                'payload' => $payload,
                'pending_checkout_id' => $pendingCheckoutId,
                'last_error' => null,
            ])->save();

            return new PaymentWebhookEventResult($event->refresh(), 'process');
        }, 3);
    }

    public function markProcessed(PaymentWebhookEvent $event, ?int $orderId = null, ?int $pendingCheckoutId = null): PaymentWebhookEvent
    {
        $event->forceFill([
            'status' => PaymentWebhookEvent::STATUS_PROCESSED,
            'order_id' => $orderId ?? $event->order_id,
            'pending_checkout_id' => $pendingCheckoutId ?? $event->pending_checkout_id,
            'processed_at' => now(),
            'last_error' => null,
        ])->save();

        return $event->refresh();
    }

    public function markIgnored(PaymentWebhookEvent $event, ?int $pendingCheckoutId = null, ?string $message = null): PaymentWebhookEvent
    {
        $event->forceFill([
            'status' => PaymentWebhookEvent::STATUS_IGNORED,
            'pending_checkout_id' => $pendingCheckoutId ?? $event->pending_checkout_id,
            'processed_at' => now(),
            'last_error' => $message,
        ])->save();

        return $event->refresh();
    }

    public function markFailed(PaymentWebhookEvent $event, \Throwable|string $error, ?int $pendingCheckoutId = null): PaymentWebhookEvent
    {
        $message = is_string($error) ? $error : $error->getMessage();

        $event->forceFill([
            'status' => PaymentWebhookEvent::STATUS_FAILED,
            'pending_checkout_id' => $pendingCheckoutId ?? $event->pending_checkout_id,
            'last_error' => $message,
        ])->save();

        return $event->refresh();
    }

    protected function processingTimeoutMinutes(): int
    {
        return max(1, (int) config('payments.webhooks.processing_timeout_minutes', 2));
    }
}
