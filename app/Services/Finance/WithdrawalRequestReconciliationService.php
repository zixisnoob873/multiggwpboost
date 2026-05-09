<?php

namespace App\Services\Finance;

use App\Models\BoosterWalletAdjustment;
use App\Models\WithdrawalRequest;
use App\Services\BoosterWalletService;
use Illuminate\Support\Facades\Log;

class WithdrawalRequestReconciliationService
{
    public const STATUS_DIRECT = 'direct';

    public const STATUS_LEGACY_MATCHED = 'legacy_matched';

    public const STATUS_LEGACY_UNMATCHED = 'legacy_unmatched';

    public function reconcile(bool $log = true): array
    {
        $summary = [
            'direct' => 0,
            'legacy_matched' => 0,
            'legacy_unmatched' => 0,
        ];

        $requests = WithdrawalRequest::query()
            ->whereIn('status', [WithdrawalRequest::STATUS_APPROVED, WithdrawalRequest::STATUS_PAID])
            ->with('walletAdjustment')
            ->orderBy('id')
            ->get();

        foreach ($requests as $request) {
            if ($request->walletAdjustment) {
                if ($request->reconciliation_status !== self::STATUS_DIRECT) {
                    $request->forceFill([
                        'reconciliation_status' => self::STATUS_DIRECT,
                        'reconciled_at' => now(),
                    ])->save();
                }

                $summary['direct']++;

                continue;
            }

            $candidate = BoosterWalletAdjustment::query()
                ->whereNull('withdrawal_request_id')
                ->where('booster_id', $request->booster_id)
                ->where('type', 'deduct')
                ->where('amount_cents', $request->amount_cents)
                ->where(function ($query) {
                    $query->where('reason', BoosterWalletService::WITHDRAWAL_APPROVAL_REASON)
                        ->orWhere('reason', 'like', '%Withdrawal%approved%');
                })
                ->get()
                ->sortBy(function (BoosterWalletAdjustment $adjustment) use ($request) {
                    $baseline = $request->processed_at ?? $request->created_at;

                    return abs($adjustment->created_at?->getTimestamp() - $baseline?->getTimestamp());
                })
                ->first();

            if ($candidate) {
                $candidate->forceFill([
                    'withdrawal_request_id' => $request->id,
                ])->save();

                $request->forceFill([
                    'reconciliation_status' => self::STATUS_LEGACY_MATCHED,
                    'reconciled_at' => now(),
                ])->save();

                $summary['legacy_matched']++;

                continue;
            }

            $request->forceFill([
                'reconciliation_status' => self::STATUS_LEGACY_UNMATCHED,
                'reconciled_at' => now(),
            ])->save();

            $summary['legacy_unmatched']++;
        }

        if ($log && array_sum($summary) > 0) {
            Log::channel('payments')->info('Reconciled historical withdrawal approvals.', $summary);
        }

        return $summary;
    }
}
