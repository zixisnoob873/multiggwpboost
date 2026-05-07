<?php

namespace App\Actions;

use App\Models\BoosterWalletAdjustment;
use App\Models\WithdrawalRequest;
use App\Services\BoosterWalletService;
use App\Services\Finance\WithdrawalRequestReconciliationService;
use App\Services\Mail\BoosterEmailNotifier;

class ProcessWithdrawalRequestAction
{
    public function __construct(
        protected BoosterWalletService $boosterWalletService,
        protected BoosterEmailNotifier $boosterEmailNotifier,
    ) {}

    public function execute(WithdrawalRequest $withdrawalRequest, string $status, int $adminId, array $details = []): array
    {
        return $this->boosterWalletService->withinLockedWallet($withdrawalRequest->booster_id, function ($booster, array $summary) use ($withdrawalRequest, $status, $adminId, $details) {
            $lockedRequest = WithdrawalRequest::query()
                ->lockForUpdate()
                ->findOrFail($withdrawalRequest->getKey());

            if ($lockedRequest->status !== WithdrawalRequest::STATUS_PENDING) {
                return [
                    'processed' => false,
                    'withdrawalRequest' => $lockedRequest,
                ];
            }

            $metadata = is_array($lockedRequest->metadata) ? $lockedRequest->metadata : [];
            $metadata = array_merge($metadata, array_filter([
                'processed_by_admin_id' => $adminId,
                'payout_method' => $this->clean($details['payout_method'] ?? null),
                'transaction_reference' => $this->clean($details['transaction_reference'] ?? null),
                'estimated_arrival' => $this->clean($details['estimated_arrival'] ?? null),
            ], fn ($value) => $value !== null && $value !== ''));

            $notes = $this->clean($details['notes'] ?? null);

            $lockedRequest->update([
                'status' => $status,
                'reconciliation_status' => $status === WithdrawalRequest::STATUS_APPROVED
                    ? WithdrawalRequestReconciliationService::STATUS_DIRECT
                    : $lockedRequest->reconciliation_status,
                'notes' => $status === WithdrawalRequest::STATUS_APPROVED
                    ? BoosterWalletService::WITHDRAWAL_APPROVAL_REASON
                    : ($notes ?: 'The request could not be approved with the current payout details.'),
                'metadata' => $metadata,
                'processed_at' => now(),
                'reconciled_at' => $status === WithdrawalRequest::STATUS_APPROVED ? now() : $lockedRequest->reconciled_at,
            ]);

            if ($status === WithdrawalRequest::STATUS_APPROVED) {
                BoosterWalletAdjustment::query()->firstOrCreate(
                    ['withdrawal_request_id' => $lockedRequest->getKey()],
                    [
                        'booster_id' => $lockedRequest->booster_id,
                        'admin_id' => $adminId,
                        'type' => 'deduct',
                        'amount_cents' => $lockedRequest->amount_cents,
                        'reason' => BoosterWalletService::WITHDRAWAL_APPROVAL_REASON,
                    ]
                );
            }

            $processedRequest = $lockedRequest->refresh()->load('booster');
            $this->boosterEmailNotifier->queueWithdrawalProcessed($processedRequest);

            return [
                'processed' => true,
                'withdrawalRequest' => $processedRequest,
            ];
        });
    }

    protected function clean(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? mb_substr($value, 0, 500) : null;
    }
}
