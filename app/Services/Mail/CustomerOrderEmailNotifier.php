<?php

namespace App\Services\Mail;

use App\Enums\CustomerOrderEmailType;
use App\Jobs\SendCustomerOrderEmailJob;
use App\Models\CustomerOrderEmailDispatch;
use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CustomerOrderEmailNotifier
{
    public function queueCreated(Order $order): ?CustomerOrderEmailDispatch
    {
        return $this->queue(CustomerOrderEmailType::CREATED, $order);
    }

    public function queueStatusChanged(Order $order, ?string $previousStatus): ?CustomerOrderEmailDispatch
    {
        $type = CustomerOrderEmailType::fromStatusChange($previousStatus, $order->status);

        if (! $type) {
            return null;
        }

        return $this->queue($type, $order, [
            'previous_status' => $previousStatus,
            'current_status' => $order->status,
        ]);
    }

    public function queue(CustomerOrderEmailType $type, Order $order, array $context = []): ?CustomerOrderEmailDispatch
    {
        $order->loadMissing('user');

        $payload = $this->buildPayload($order, $type, $context);
        $recipientEmail = trim((string) data_get($payload, 'customer.email', ''));

        if (filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) === false) {
            Log::channel('customer_mail')->warning('Skipped customer order email because no valid recipient email was available.', [
                'email_type' => $type->value,
                'order_id' => $order->getKey(),
                'user_id' => $order->user_id,
            ]);

            return null;
        }

        $dispatch = CustomerOrderEmailDispatch::query()->firstOrCreate(
            ['fingerprint' => $this->fingerprint($order, $type, $payload, $context)],
            [
                'order_id' => $order->getKey(),
                'user_id' => $order->user_id,
                'recipient_email' => $recipientEmail,
                'recipient_name' => trim((string) data_get($payload, 'customer.name', '')),
                'email_type' => $type->value,
                'mailable' => $type->mailableClass(),
                'payload' => $payload,
                'context' => array_merge($context, [
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'email_type' => $type->value,
                ]),
                'status' => CustomerOrderEmailDispatch::STATUS_PENDING,
            ]
        );

        if ($dispatch->wasRecentlyCreated || $this->shouldQueueDispatch($dispatch)) {
            SendCustomerOrderEmailJob::dispatch($dispatch->id)->afterCommit();

            Log::channel('customer_mail')->info('Queued customer order email.', [
                'dispatch_id' => $dispatch->id,
                'email_type' => $type->value,
                'order_id' => $order->getKey(),
                'user_id' => $order->user_id,
                'queue' => config('payments.customer_order_emails.queue', 'notifications'),
            ]);
        }

        return $dispatch->refresh();
    }

    public function makeMailable(CustomerOrderEmailDispatch $dispatch): Mailable
    {
        $type = CustomerOrderEmailType::from($dispatch->email_type);

        return app()->make($type->mailableClass(), [
            'payload' => (array) $dispatch->payload,
        ]);
    }

    protected function shouldQueueDispatch(CustomerOrderEmailDispatch $dispatch): bool
    {
        if ($dispatch->status === CustomerOrderEmailDispatch::STATUS_FAILED) {
            return true;
        }

        if (! in_array($dispatch->status, [
            CustomerOrderEmailDispatch::STATUS_PENDING,
            CustomerOrderEmailDispatch::STATUS_PROCESSING,
        ], true)) {
            return false;
        }

        $retryAfter = now()->subMinutes(max(1, (int) config('payments.customer_order_emails.retry_failed_after_minutes', 10)));

        return ! $dispatch->updated_at || $dispatch->updated_at->lte($retryAfter);
    }

    protected function buildPayload(Order $order, CustomerOrderEmailType $type, array $context = []): array
    {
        $metadata = is_array($order->metadata) ? $order->metadata : (json_decode((string) $order->metadata, true) ?: []);
        $metaCustomer = is_array($metadata['customer'] ?? null) ? $metadata['customer'] : [];
        $lifecycle = $this->lifecyclePayload($order, $type, $metadata);
        $notificationTimestamp = $this->notificationTimestamp($order, $type);
        $customerEmail = $this->customerEmail($order, $metaCustomer);

        return [
            'email' => [
                'type' => $type->value,
                'label' => $type->label(),
            ],
            'branding' => $this->brandingPayload(),
            'customer' => [
                'name' => $this->customerName($order, $metaCustomer),
                'email' => $customerEmail,
            ],
            'order' => [
                'id' => $order->getKey(),
                'number' => (string) ($order->order_number ?? $order->getKey()),
                'product' => (string) ($order->product ?? ''),
                'service_name' => $order->serviceName(),
                'status' => (string) $order->status,
                'status_label' => $order->statusLabel(),
                'previous_status' => $context['previous_status'] ?? null,
                'previous_status_label' => isset($context['previous_status'])
                    ? \App\Support\OrderStatus::label((string) $context['previous_status'])
                    : null,
                'is_paid' => (string) ($order->payment_status ?? '') === 'paid',
                'payment_status' => (string) ($order->payment_status ?? ''),
                'payment_method' => $this->paymentProviderLabel((string) data_get($metadata, 'paymentProvider', data_get($metadata, 'paymentMethod', ''))),
                'payment_reference' => (string) ($order->payment_reference ?? ''),
                'price_cents' => $order->customerPriceCents(),
                'price_formatted' => $this->formatMoney($order->customerPriceCents(), (string) ($order->currency ?? 'USD')),
                'currency' => (string) ($order->currency ?? 'USD'),
                'task_label' => $order->taskLabel(),
                'scope_label' => $this->scopeLabel($order),
                'rank_from' => $order->rankFromLabel(),
                'rank_to' => $order->rankToLabel(),
                'region' => $order->regionLabel(),
                'addons' => $order->addonsList(),
                'addons_label' => $order->addonsLabel(),
                'contact_method' => (string) ($order->contact_method ?? ''),
                'created_at' => $order->created_at?->toIso8601String(),
                'created_at_formatted' => $this->formatTimestamp($order->created_at),
                'paid_at' => $order->paid_at?->toIso8601String(),
                'paid_at_formatted' => $this->formatTimestamp($order->paid_at),
                'assigned_at' => $order->assigned_at?->toIso8601String(),
                'assigned_at_formatted' => $this->formatTimestamp($order->assigned_at),
                'completed_at' => $order->completed_at?->toIso8601String(),
                'completed_at_formatted' => $this->formatTimestamp($order->completed_at),
                'completion_proof_uploaded_at' => $order->completion_proof_uploaded_at?->toIso8601String(),
                'completion_proof_uploaded_at_formatted' => $this->formatTimestamp($order->completion_proof_uploaded_at),
                'completion_proof_available' => is_string($order->completion_proof_path) && trim($order->completion_proof_path) !== '',
                'result_summary' => $this->resultSummary($order, $metadata),
                'notification_timestamp' => $notificationTimestamp?->toIso8601String(),
                'notification_timestamp_formatted' => $this->formatTimestamp($notificationTimestamp),
                'lifecycle' => $lifecycle,
                'refund' => $this->refundPayload($order, $metadata, $lifecycle),
            ],
            'links' => [
                'order_url' => route('user-chats.show', ['order' => $order]),
                'orders_url' => route('allorders'),
                'support_url' => route('contact'),
                'support_email' => $this->supportEmail(),
                'community_url' => config('footer.support.community_url'),
            ],
        ];
    }

    protected function brandingPayload(): array
    {
        $branding = [
            'app_name' => config('app.name', 'GGWP Boost'),
        ];

        $logoUrl = trim((string) config('mail.logo_url', ''));

        if ($logoUrl !== '') {
            $branding['logo_url'] = $logoUrl;
        }

        return $branding;
    }

    protected function customerName(Order $order, array $metaCustomer): string
    {
        $metaName = trim(implode(' ', array_filter([
            trim((string) ($metaCustomer['firstName'] ?? '')),
            trim((string) ($metaCustomer['lastName'] ?? '')),
        ])));

        if ($order->user) {
            return $order->user->fullIdentity($metaName !== '' ? $metaName : 'Customer');
        }

        return $metaName !== '' ? $metaName : 'Customer';
    }

    protected function customerEmail(Order $order, array $metaCustomer): ?string
    {
        $email = trim((string) ($order->user?->email ?? ($metaCustomer['email'] ?? '')));

        return $email !== '' ? $email : null;
    }

    protected function notificationTimestamp(Order $order, CustomerOrderEmailType $type): ?Carbon
    {
        $metadata = is_array($order->metadata) ? $order->metadata : (json_decode((string) $order->metadata, true) ?: []);
        $event = $this->lifecyclePayload($order, $type, $metadata);
        $changedAt = $this->parseTimestamp((string) ($event['changed_at'] ?? ''));

        if ($changedAt) {
            return $changedAt;
        }

        return match ($type) {
            CustomerOrderEmailType::CREATED => $order->created_at ?? $order->paid_at ?? $order->updated_at,
            CustomerOrderEmailType::ASSIGNED => $order->assigned_at ?? $order->updated_at,
            CustomerOrderEmailType::COMPLETED => $order->completed_at ?? $order->updated_at,
            default => $order->updated_at ?? $order->created_at,
        };
    }

    protected function formatMoney(int $amountCents, string $currency): string
    {
        $currency = strtoupper(trim($currency)) ?: 'USD';
        $amount = number_format($amountCents / 100, 2);

        return $currency === 'USD' ? '$'.$amount : "{$currency} {$amount}";
    }

    protected function formatTimestamp(?Carbon $timestamp): ?string
    {
        return $timestamp?->format('M j, Y g:i A T');
    }

    protected function supportEmail(): ?string
    {
        $supportEmail = trim((string) (config('footer.support.email') ?? config('mail.from.address') ?? ''));

        return $supportEmail !== '' ? $supportEmail : null;
    }

    protected function lifecyclePayload(Order $order, CustomerOrderEmailType $type, array $metadata): array
    {
        $key = match ($type) {
            CustomerOrderEmailType::ASSIGNED => 'assigned',
            CustomerOrderEmailType::PAUSED => 'paused',
            CustomerOrderEmailType::RESUMED => 'resumed',
            CustomerOrderEmailType::COMPLETED => 'completed',
            CustomerOrderEmailType::CANCELLED => 'cancelled',
            CustomerOrderEmailType::REFUNDED => 'refunded',
            default => null,
        };

        if ($key === null) {
            return [];
        }

        $event = data_get($metadata, 'lifecycle.'.$key, []);

        return is_array($event) ? $event : [];
    }

    protected function refundPayload(Order $order, array $metadata, array $lifecycle): array
    {
        $refund = data_get($lifecycle, 'refund', []);
        $refund = is_array($refund) ? $refund : [];
        $amountCents = (int) ($refund['amount_cents'] ?? $order->customerPriceCents());

        return [
            'amount_cents' => $amountCents,
            'amount_formatted' => $this->formatMoney($amountCents, (string) ($order->currency ?? 'USD')),
            'method' => (string) ($refund['method'] ?? $this->paymentProviderLabel((string) data_get($metadata, 'paymentProvider', data_get($metadata, 'paymentMethod', '')))),
            'destination' => (string) ($refund['destination'] ?? 'Original payment method'),
            'estimated_arrival' => (string) ($refund['estimated_arrival'] ?? 'Usually 5-10 business days; provider and bank timing can vary.'),
            'reference' => (string) ($refund['reference'] ?? ''),
        ];
    }

    protected function scopeLabel(Order $order): string
    {
        $taskLabel = trim($order->taskLabel());

        if ($taskLabel !== '' && $taskLabel !== '-') {
            return $taskLabel;
        }

        $from = trim($order->rankFromLabel());
        $to = trim($order->rankToLabel());

        if ($from !== '' && $from !== '-' && $to !== '' && $to !== '-') {
            return "{$from} to {$to}";
        }

        return '-';
    }

    protected function resultSummary(Order $order, array $metadata): ?string
    {
        $summary = trim((string) data_get($metadata, 'lifecycle.completed.result_summary', ''));

        if ($summary !== '') {
            return $summary;
        }

        if ($order->completed_at) {
            return 'Marked complete in your GGWP Boost dashboard.';
        }

        return null;
    }

    protected function paymentProviderLabel(string $provider): string
    {
        $provider = trim($provider);

        return $provider !== '' ? \Illuminate\Support\Str::headline($provider) : 'Original payment method';
    }

    protected function parseTimestamp(string $timestamp): ?Carbon
    {
        $timestamp = trim($timestamp);

        if ($timestamp === '') {
            return null;
        }

        try {
            return Carbon::parse($timestamp);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function fingerprint(Order $order, CustomerOrderEmailType $type, array $payload, array $context): string
    {
        return hash('sha256', json_encode([
            'order_id' => $order->getKey(),
            'email_type' => $type->value,
            'previous_status' => $context['previous_status'] ?? null,
            'current_status' => $order->status,
            'notification_timestamp' => data_get($payload, 'order.notification_timestamp'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
