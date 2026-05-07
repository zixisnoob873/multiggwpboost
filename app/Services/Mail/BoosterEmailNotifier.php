<?php

namespace App\Services\Mail;

use App\Mail\Transactional\BoosterAssignedOrderMail;
use App\Mail\Transactional\WithdrawalApprovedMail;
use App\Mail\Transactional\WithdrawalRejectedMail;
use App\Models\Order;
use App\Models\WithdrawalRequest;

class BoosterEmailNotifier
{
    public function __construct(protected TransactionalMailDispatcher $transactionalMailDispatcher) {}

    public function queueOrderAssignedByAdmin(Order $order): bool
    {
        $order->loadMissing(['booster', 'user']);

        if (! $order->booster) {
            return false;
        }

        $payload = $this->orderAssignmentPayload($order);

        return $this->transactionalMailDispatcher->queue(
            $order->booster->email,
            new BoosterAssignedOrderMail($payload),
            $order->booster->fullIdentity('Booster'),
            $this->fingerprint('booster-assigned-order', [
                'order_id' => $order->getKey(),
                'booster_id' => $order->booster_id,
                'assigned_at' => $order->assigned_at?->toIso8601String(),
            ]),
            ['email_type' => 'booster_assigned_order', 'order_id' => $order->getKey()],
        );
    }

    public function queueWithdrawalProcessed(WithdrawalRequest $withdrawalRequest): bool
    {
        $withdrawalRequest->loadMissing('booster');

        if (! $withdrawalRequest->booster) {
            return false;
        }

        $mailClass = match ($withdrawalRequest->status) {
            WithdrawalRequest::STATUS_APPROVED => WithdrawalApprovedMail::class,
            WithdrawalRequest::STATUS_REJECTED => WithdrawalRejectedMail::class,
            default => null,
        };

        if ($mailClass === null) {
            return false;
        }

        $payload = $this->withdrawalPayload($withdrawalRequest);

        return $this->transactionalMailDispatcher->queue(
            $withdrawalRequest->booster->email,
            new $mailClass($payload),
            $withdrawalRequest->booster->fullIdentity('Booster'),
            $this->fingerprint('withdrawal-processed', [
                'withdrawal_id' => $withdrawalRequest->getKey(),
                'status' => $withdrawalRequest->status,
                'processed_at' => $withdrawalRequest->processed_at?->toIso8601String(),
            ]),
            ['email_type' => 'withdrawal_'.$withdrawalRequest->status, 'withdrawal_id' => $withdrawalRequest->getKey()],
        );
    }

    protected function orderAssignmentPayload(Order $order): array
    {
        $supportEmail = trim((string) (config('footer.support.email') ?? config('mail.from.address') ?? ''));

        return [
            'booster' => [
                'name' => $order->booster?->fullIdentity('Booster'),
                'email' => $order->booster?->email,
            ],
            'order' => [
                'id' => $order->getKey(),
                'number' => (string) ($order->order_number ?? $order->getKey()),
                'service_name' => $order->serviceName(),
                'status' => (string) $order->status,
                'status_label' => $order->statusLabel(),
                'customer_total_formatted' => $this->formatMoney($order->customerPriceCents(), (string) ($order->currency ?? 'USD')),
                'payout_formatted' => $this->formatMoney($order->resolvedBoosterPayoutCents(), (string) ($order->currency ?? 'USD')),
                'payout_basis_formatted' => $this->formatMoney($order->resolvedBoosterPayoutBasisCents(), (string) ($order->currency ?? 'USD')),
                'task_label' => $order->taskLabel(),
                'rank_from' => $order->rankFromLabel(),
                'rank_to' => $order->rankToLabel(),
                'region' => $order->regionLabel(),
                'addons_label' => $order->addonsLabel(),
                'assigned_at' => $order->assigned_at?->toIso8601String(),
                'assigned_at_formatted' => $order->assigned_at?->format('M j, Y g:i A T'),
            ],
            'customer' => [
                'name' => $order->user?->publicIdentity('Customer'),
            ],
            'links' => [
                'order_url' => route('booster-chats.show', ['order' => $order]),
                'orders_url' => route('booster-orders'),
                'dashboard_url' => route('booster-dashboard'),
                'support_url' => route('contact'),
                'support_email' => $supportEmail !== '' ? $supportEmail : null,
            ],
            'branding' => $this->brandingPayload(),
        ];
    }

    protected function withdrawalPayload(WithdrawalRequest $withdrawalRequest): array
    {
        $supportEmail = trim((string) (config('footer.support.email') ?? config('mail.from.address') ?? ''));
        $metadata = is_array($withdrawalRequest->metadata) ? $withdrawalRequest->metadata : [];
        $notes = trim((string) ($withdrawalRequest->notes ?? ''));

        if (strtolower($notes) === 'withdrawal request rejected' || strtolower($notes) === strtolower(\App\Services\BoosterWalletService::WITHDRAWAL_APPROVAL_REASON)) {
            $notes = '';
        }

        $estimatedArrival = trim((string) data_get($metadata, 'estimated_arrival', ''));
        if ($estimatedArrival === '' && $withdrawalRequest->status === WithdrawalRequest::STATUS_APPROVED) {
            $estimatedArrival = 'Usually 1-3 business days after approval.';
        }

        return [
            'booster' => [
                'name' => $withdrawalRequest->booster?->fullIdentity('Booster'),
                'email' => $withdrawalRequest->booster?->email,
            ],
            'withdrawal' => [
                'id' => $withdrawalRequest->getKey(),
                'status' => $withdrawalRequest->status,
                'amount_cents' => (int) $withdrawalRequest->amount_cents,
                'amount_formatted' => $this->formatMoney((int) $withdrawalRequest->amount_cents, 'USD'),
                'processed_at' => $withdrawalRequest->processed_at?->toIso8601String(),
                'processed_at_formatted' => $withdrawalRequest->processed_at?->format('M j, Y g:i A T'),
                'notes' => $notes !== '' ? $notes : null,
                'rejection_reason' => $notes !== '' ? $notes : 'The request could not be approved with the current payout details.',
                'payout_method' => trim((string) data_get($metadata, 'payout_method', '')) ?: 'Manual payout',
                'estimated_arrival' => $estimatedArrival !== '' ? $estimatedArrival : null,
                'transaction_reference' => trim((string) data_get($metadata, 'transaction_reference', '')) ?: null,
                'can_resubmit' => $withdrawalRequest->status === WithdrawalRequest::STATUS_REJECTED,
                'next_step' => $withdrawalRequest->status === WithdrawalRequest::STATUS_REJECTED
                    ? 'Review the reason, update the payout details if needed, then submit a new request from your wallet.'
                    : 'Keep an eye on your payout account; arrival timing can vary by provider.',
            ],
            'links' => [
                'wallet_url' => route('booster-wallet'),
                'support_url' => route('contact'),
                'support_email' => $supportEmail !== '' ? $supportEmail : null,
            ],
            'branding' => $this->brandingPayload(),
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

    protected function formatMoney(int $amountCents, string $currency): string
    {
        $currency = strtoupper(trim($currency)) ?: 'USD';
        $amount = number_format($amountCents / 100, 2);

        return $currency === 'USD' ? '$'.$amount : "{$currency} {$amount}";
    }

    protected function fingerprint(string $event, array $context): string
    {
        return hash('sha256', json_encode([
            'event' => $event,
            ...$context,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
