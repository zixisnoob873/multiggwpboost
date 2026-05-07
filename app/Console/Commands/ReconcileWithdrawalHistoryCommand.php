<?php

namespace App\Console\Commands;

use App\Services\Finance\WithdrawalRequestReconciliationService;
use Illuminate\Console\Command;

class ReconcileWithdrawalHistoryCommand extends Command
{
    protected $signature = 'finance:reconcile-withdrawals';

    protected $description = 'Backfill and reconcile historical withdrawal approvals with wallet deductions.';

    public function handle(WithdrawalRequestReconciliationService $service): int
    {
        $summary = $service->reconcile();

        $this->table(['State', 'Count'], [
            [WithdrawalRequestReconciliationService::STATUS_DIRECT, $summary['direct']],
            [WithdrawalRequestReconciliationService::STATUS_LEGACY_MATCHED, $summary['legacy_matched']],
            [WithdrawalRequestReconciliationService::STATUS_LEGACY_UNMATCHED, $summary['legacy_unmatched']],
        ]);

        return self::SUCCESS;
    }
}
