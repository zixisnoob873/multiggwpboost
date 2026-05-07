<?php

namespace Tests\Feature;

use App\Actions\ProcessWithdrawalRequestAction;
use App\Actions\SubmitWithdrawalRequestAction;
use App\Models\BoosterWalletAdjustment;
use App\Models\Order;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\BoosterWalletService;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WithdrawalConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_second_withdrawal_request_cannot_spend_the_same_available_balance(): void
    {
        $booster = User::factory()->create([
            'role' => 'booster',
            'account_status' => 'active',
        ]);

        $this->createCompletedOrderForBooster($booster, 10000);

        $firstActor = User::query()->findOrFail($booster->id);
        $secondActor = User::query()->findOrFail($booster->id);

        $firstRequest = app(SubmitWithdrawalRequestAction::class)->execute($firstActor, 60.00);

        $this->assertSame(WithdrawalRequest::STATUS_PENDING, $firstRequest->status);
        $this->assertSame(6000, $firstRequest->amount_cents);

        try {
            app(SubmitWithdrawalRequestAction::class)->execute($secondActor, 60.00);
            $this->fail('Expected the second withdrawal request to be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertSame('Requested amount exceeds your available balance.', $exception->getMessage());
        }

        $this->assertSame(1, WithdrawalRequest::query()->count());
        $this->assertSame(4000, app(BoosterWalletService::class)->availableBalanceCentsForBooster($booster->fresh()));
    }

    public function test_double_approval_is_idempotent_and_creates_only_one_wallet_deduction(): void
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
            'status' => WithdrawalRequest::STATUS_PENDING,
        ]);

        $firstAttempt = WithdrawalRequest::query()->findOrFail($withdrawalRequest->id);
        $secondAttempt = WithdrawalRequest::query()->findOrFail($withdrawalRequest->id);

        $firstResult = app(ProcessWithdrawalRequestAction::class)->execute(
            $firstAttempt,
            WithdrawalRequest::STATUS_APPROVED,
            $admin->id,
        );

        $this->assertTrue($firstResult['processed']);

        $secondResult = app(ProcessWithdrawalRequestAction::class)->execute(
            $secondAttempt,
            WithdrawalRequest::STATUS_APPROVED,
            $admin->id,
        );

        $this->assertFalse($secondResult['processed']);
        $this->assertSame(1, BoosterWalletAdjustment::query()->count());

        $adjustment = BoosterWalletAdjustment::query()->firstOrFail();
        $withdrawalRequest->refresh();

        $this->assertSame($withdrawalRequest->id, $adjustment->withdrawal_request_id);
        $this->assertSame($booster->id, $adjustment->booster_id);
        $this->assertSame(5000, $adjustment->amount_cents);
        $this->assertSame(WithdrawalRequest::STATUS_APPROVED, $withdrawalRequest->status);
        $this->assertNotNull($withdrawalRequest->processed_at);
    }

    public function test_wallet_summary_uses_live_ledger_semantics_and_exposes_snapshot_metadata(): void
    {
        $booster = User::factory()->create([
            'role' => 'booster',
            'account_status' => 'active',
        ]);

        $this->createCompletedOrderForBooster($booster, 10000);

        WithdrawalRequest::query()->create([
            'booster_id' => $booster->id,
            'amount_cents' => 2500,
            'status' => WithdrawalRequest::STATUS_PENDING,
        ]);

        $summary = app(BoosterWalletService::class)->summaryForBooster($booster->fresh());

        $this->assertSame('live_ledger', $summary['balance_model']);
        $this->assertNotNull($summary['balance_snapshot_at']);
        $this->assertSame(10000, $summary['current_balance_cents']);
        $this->assertSame(7500, $summary['available_balance_cents']);
        $this->assertSame(2500, $summary['pending_withdrawal_cents']);
    }

    protected function createCompletedOrderForBooster(User $booster, int $boosterPayoutCents): Order
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        return Order::query()->create([
            'user_id' => $customer->id,
            'booster_id' => $booster->id,
            'order_number' => (string) Str::orderedUuid(),
            'product' => 'Rank Boosting',
            'status' => OrderStatus::COMPLETED,
            'payment_status' => 'paid',
            'price_cents' => 20000,
            'booster_payout_rate' => 50,
            'booster_payout_cents' => $boosterPayoutCents,
            'currency' => 'USD',
            'details' => [
                'order' => [
                    'orderType' => 'Rank Boosting',
                    'currentDivision' => 'Gold I',
                    'desiredDivision' => 'Platinum I',
                ],
            ],
            'assigned_at' => now()->subDay(),
        ]);
    }
}
