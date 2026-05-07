<?php

namespace Tests\Feature;

use App\Models\BoosterWalletAdjustment;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\BoosterWalletService;
use App\Services\Finance\WithdrawalRequestReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_historical_withdrawal_approval_is_backfilled_to_matching_wallet_adjustment(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $booster = User::factory()->create([
            'role' => 'booster',
            'account_status' => 'active',
        ]);

        $withdrawalRequest = WithdrawalRequest::query()->create([
            'booster_id' => $booster->id,
            'amount_cents' => 5000,
            'status' => WithdrawalRequest::STATUS_APPROVED,
            'processed_at' => now()->subMinutes(5),
        ]);

        $adjustment = BoosterWalletAdjustment::query()->create([
            'booster_id' => $booster->id,
            'admin_id' => $admin->id,
            'type' => 'deduct',
            'amount_cents' => 5000,
            'reason' => BoosterWalletService::WITHDRAWAL_APPROVAL_REASON,
            'withdrawal_request_id' => null,
            'created_at' => now()->subMinutes(4),
            'updated_at' => now()->subMinutes(4),
        ]);

        $summary = app(WithdrawalRequestReconciliationService::class)->reconcile(false);

        $withdrawalRequest->refresh();
        $adjustment->refresh();

        $this->assertSame(1, $summary['legacy_matched']);
        $this->assertSame($withdrawalRequest->id, $adjustment->withdrawal_request_id);
        $this->assertSame(WithdrawalRequestReconciliationService::STATUS_LEGACY_MATCHED, $withdrawalRequest->reconciliation_status);
        $this->assertNotNull($withdrawalRequest->reconciled_at);
    }

    public function test_unmatched_historical_withdrawal_is_marked_for_manual_audit(): void
    {
        $booster = User::factory()->create([
            'role' => 'booster',
            'account_status' => 'active',
        ]);

        $withdrawalRequest = WithdrawalRequest::query()->create([
            'booster_id' => $booster->id,
            'amount_cents' => 4200,
            'status' => WithdrawalRequest::STATUS_APPROVED,
            'processed_at' => now()->subHour(),
        ]);

        $summary = app(WithdrawalRequestReconciliationService::class)->reconcile(false);

        $withdrawalRequest->refresh();

        $this->assertSame(1, $summary['legacy_unmatched']);
        $this->assertSame(WithdrawalRequestReconciliationService::STATUS_LEGACY_UNMATCHED, $withdrawalRequest->reconciliation_status);
        $this->assertNotNull($withdrawalRequest->reconciled_at);
    }
}
