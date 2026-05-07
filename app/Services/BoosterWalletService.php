<?php

namespace App\Services;

use App\Models\BoosterWalletAdjustment;
use App\Models\Order;
use App\Models\OrderTip;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Support\OrderStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BoosterWalletService
{
    public const WITHDRAWAL_APPROVAL_REASON = 'Withdrawal Requests approved';

    public function summaryForBooster(User $booster): array
    {
        $snapshot = $this->snapshotForBooster($booster->id);
        $metrics = $this->summaryMetricsForBooster($booster->id);

        return array_merge($this->buildSummaryFromSnapshot($snapshot), $metrics, [
            'balance_snapshot_at' => now(),
            'balance_model' => 'live_ledger',
        ]);
    }

    public function availableBalanceCentsForBooster(User $booster): int
    {
        return $this->summaryForBooster($booster)['available_balance_cents'];
    }

    public function availableBalanceCentsForBoosters(iterable $boosters): array
    {
        $boosterIds = collect($boosters)
            ->pluck('id')
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($boosterIds->isEmpty()) {
            return [];
        }

        $payoutExpression = Order::boosterPayoutSql();
        $completedEarnings = Order::query()
            ->whereIn('booster_id', $boosterIds)
            ->where('status', OrderStatus::COMPLETED)
            ->select('booster_id')
            ->selectRaw("SUM({$payoutExpression}) as aggregate")
            ->groupBy('booster_id')
            ->pluck('aggregate', 'booster_id');
        $approvedWithdrawals = WithdrawalRequest::query()
            ->whereIn('booster_id', $boosterIds)
            ->whereIn('status', [WithdrawalRequest::STATUS_APPROVED, WithdrawalRequest::STATUS_PAID])
            ->select('booster_id')
            ->selectRaw('SUM(amount_cents) as aggregate')
            ->groupBy('booster_id')
            ->pluck('aggregate', 'booster_id');
        $pendingWithdrawals = WithdrawalRequest::query()
            ->whereIn('booster_id', $boosterIds)
            ->where('status', WithdrawalRequest::STATUS_PENDING)
            ->select('booster_id')
            ->selectRaw('SUM(amount_cents) as aggregate')
            ->groupBy('booster_id')
            ->pluck('aggregate', 'booster_id');
        $adjustments = BoosterWalletAdjustment::query()
            ->whereIn('booster_id', $boosterIds)
            ->whereNull('withdrawal_request_id')
            ->where('reason', '<>', self::WITHDRAWAL_APPROVAL_REASON)
            ->select('booster_id')
            ->selectRaw("SUM(CASE WHEN type = 'add' THEN amount_cents ELSE amount_cents * -1 END) as aggregate")
            ->groupBy('booster_id')
            ->pluck('aggregate', 'booster_id');
        $tips = collect();

        if (Schema::hasTable('order_tips')) {
            $tips = OrderTip::query()
                ->whereIn('booster_id', $boosterIds)
                ->where('recipient_type', OrderTip::RECIPIENT_BOOSTER)
                ->select('booster_id')
                ->selectRaw('SUM(amount_cents) as aggregate')
                ->groupBy('booster_id')
                ->pluck('aggregate', 'booster_id');
        }

        return $boosterIds
            ->mapWithKeys(function (int $boosterId) use ($completedEarnings, $approvedWithdrawals, $pendingWithdrawals, $adjustments, $tips): array {
                $currentBalanceCents = max(
                    0,
                    (int) ($completedEarnings[$boosterId] ?? 0)
                    + (int) ($adjustments[$boosterId] ?? 0)
                    + (int) ($tips[$boosterId] ?? 0)
                );
                $availableBalanceCents = max(
                    0,
                    $currentBalanceCents
                    - (int) ($approvedWithdrawals[$boosterId] ?? 0)
                    - (int) ($pendingWithdrawals[$boosterId] ?? 0)
                );

                return [$boosterId => $availableBalanceCents];
            })
            ->all();
    }

    public function withinLockedWallet(User|int $booster, callable $callback): mixed
    {
        $boosterId = $booster instanceof User ? $booster->id : $booster;

        return DB::transaction(function () use ($boosterId, $callback) {
            $lockedBooster = User::query()
                ->lockForUpdate()
                ->findOrFail($boosterId);

            $summary = $this->buildSummaryFromSnapshot(
                $this->snapshotForBooster($lockedBooster->id, lockRows: true, withRelations: false)
            );

            return $callback($lockedBooster, $summary);
        }, 3);
    }

    public function buildSummary(
        Collection $completedOrders,
        Collection $activeOrders,
        Collection $withdrawalRequests,
        Collection $walletAdjustments,
        Collection $tips
    ): array {
        $totalEarnedCents = $completedOrders->sum(fn (Order $order) => $order->resolvedBoosterPayoutCents());
        $pendingEarningsCents = $activeOrders->sum(fn (Order $order) => $order->resolvedBoosterPayoutCents());
        $totalTipsCents = $tips->sum(fn (OrderTip $tip) => (int) $tip->amount_cents);
        $totalWithdrawnCents = $withdrawalRequests
            ->whereIn('status', [WithdrawalRequest::STATUS_APPROVED, WithdrawalRequest::STATUS_PAID])
            ->sum('amount_cents');
        $pendingWithdrawalCents = $withdrawalRequests
            ->where('status', WithdrawalRequest::STATUS_PENDING)
            ->sum('amount_cents');
        $totalAdjustmentCents = $walletAdjustments
            ->reject(fn (BoosterWalletAdjustment $adjustment) => $adjustment->withdrawal_request_id !== null || $adjustment->reason === self::WITHDRAWAL_APPROVAL_REASON)
            ->sum(fn (BoosterWalletAdjustment $adjustment) => $adjustment->signedAmountCents());
        $currentBalanceCents = max(0, $totalEarnedCents + $totalAdjustmentCents + $totalTipsCents);
        $availableBalanceCents = max(0, $currentBalanceCents - $totalWithdrawnCents - $pendingWithdrawalCents);

        return [
            'completed_orders' => $completedOrders,
            'active_orders' => $activeOrders,
            'withdrawal_requests' => $withdrawalRequests,
            'wallet_adjustments' => $walletAdjustments,
            'tips' => $tips,
            'total_earned_cents' => $totalEarnedCents,
            'pending_earnings_cents' => $pendingEarningsCents,
            'total_tip_cents' => $totalTipsCents,
            'total_withdrawn_cents' => $totalWithdrawnCents,
            'pending_withdrawal_cents' => $pendingWithdrawalCents,
            'total_adjustment_cents' => $totalAdjustmentCents,
            'current_balance_cents' => $currentBalanceCents,
            'available_balance_cents' => $availableBalanceCents,
        ];
    }

    protected function buildSummaryFromSnapshot(array $snapshot): array
    {
        $orders = $snapshot['orders'];

        return $this->buildSummary(
            $orders->where('status', OrderStatus::COMPLETED)->values(),
            $orders->filter(fn (Order $order) => in_array($order->status, OrderStatus::activeValues(), true))->values(),
            $snapshot['withdrawalRequests'],
            $snapshot['walletAdjustments'],
            $snapshot['tips'],
        );
    }

    protected function snapshotForBooster(int $boosterId, bool $lockRows = false, bool $withRelations = true): array
    {
        $ordersQuery = Order::query()
            ->where('booster_id', $boosterId)
            ->latest('created_at');
        $withdrawalRequestsQuery = WithdrawalRequest::query()
            ->where('booster_id', $boosterId)
            ->latest('created_at');
        $walletAdjustmentsQuery = BoosterWalletAdjustment::query()
            ->where('booster_id', $boosterId)
            ->latest('created_at');
        $tipsQuery = null;

        if (Schema::hasTable('order_tips')) {
            $tipsQuery = OrderTip::query()
                ->where('booster_id', $boosterId)
                ->where('recipient_type', OrderTip::RECIPIENT_BOOSTER)
                ->latest('created_at');
        }

        if ($withRelations) {
            $ordersQuery->with('user');
            $walletAdjustmentsQuery->with('admin');
            $tipsQuery?->with('order');
        }

        if ($lockRows) {
            $ordersQuery->lockForUpdate();
            $withdrawalRequestsQuery->lockForUpdate();
            $walletAdjustmentsQuery->lockForUpdate();
            $tipsQuery?->lockForUpdate();
        }

        return [
            'orders' => $ordersQuery->get(),
            'withdrawalRequests' => $withdrawalRequestsQuery->get(),
            'walletAdjustments' => $walletAdjustmentsQuery->get(),
            'tips' => $tipsQuery?->get() ?? collect(),
        ];
    }

    protected function summaryMetricsForBooster(int $boosterId): array
    {
        $payoutColumns = [
            'booster_payout_cents',
            'booster_payout_basis_cents',
            'booster_payout_rate',
            'price_cents',
            'original_price_cents',
            'discount_amount',
        ];

        $totalEarnedCents = (int) Order::query()
            ->where('booster_id', $boosterId)
            ->where('status', OrderStatus::COMPLETED)
            ->get($payoutColumns)
            ->sum(fn (Order $order): int => $order->resolvedBoosterPayoutCents());

        $pendingEarningsCents = (int) Order::query()
            ->where('booster_id', $boosterId)
            ->whereIn('status', OrderStatus::activeValues())
            ->get($payoutColumns)
            ->sum(fn (Order $order): int => $order->resolvedBoosterPayoutCents());

        $totalWithdrawnCents = (int) WithdrawalRequest::query()
            ->where('booster_id', $boosterId)
            ->whereIn('status', [WithdrawalRequest::STATUS_APPROVED, WithdrawalRequest::STATUS_PAID])
            ->sum('amount_cents');

        $pendingWithdrawalCents = (int) WithdrawalRequest::query()
            ->where('booster_id', $boosterId)
            ->where('status', WithdrawalRequest::STATUS_PENDING)
            ->sum('amount_cents');

        $totalAdjustmentCents = (int) BoosterWalletAdjustment::query()
            ->where('booster_id', $boosterId)
            ->whereNull('withdrawal_request_id')
            ->where('reason', '<>', self::WITHDRAWAL_APPROVAL_REASON)
            ->get(['type', 'amount_cents'])
            ->sum(fn (BoosterWalletAdjustment $adjustment): int => $adjustment->signedAmountCents());

        $totalTipsCents = Schema::hasTable('order_tips')
            ? (int) OrderTip::query()
                ->where('booster_id', $boosterId)
                ->where('recipient_type', OrderTip::RECIPIENT_BOOSTER)
                ->sum('amount_cents')
            : 0;

        $currentBalanceCents = max(0, $totalEarnedCents + $totalAdjustmentCents + $totalTipsCents);
        $availableBalanceCents = max(0, $currentBalanceCents - $totalWithdrawnCents - $pendingWithdrawalCents);

        return [
            'total_earned_cents' => $totalEarnedCents,
            'pending_earnings_cents' => $pendingEarningsCents,
            'total_tip_cents' => $totalTipsCents,
            'total_withdrawn_cents' => $totalWithdrawnCents,
            'pending_withdrawal_cents' => $pendingWithdrawalCents,
            'total_adjustment_cents' => $totalAdjustmentCents,
            'current_balance_cents' => $currentBalanceCents,
            'available_balance_cents' => $availableBalanceCents,
        ];
    }
}
