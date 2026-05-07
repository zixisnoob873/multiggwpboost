<?php

namespace App\Queries;

use App\Models\Order;
use App\Models\User;
use App\Support\OrderStatus;

class BoosterDashboardQuery
{
    public function execute(User $user): array
    {
        $payoutExpression = Order::boosterPayoutSql();
        $payoutBasisExpression = Order::boosterPayoutBasisSql();

        $summary = Order::query()
            ->where('booster_id', $user->id)
            ->selectRaw('COUNT(*) as total_assigned_orders')
            ->selectRaw(sprintf(
                'SUM(CASE WHEN status IN (%s) THEN 1 ELSE 0 END) as active_orders_count',
                $this->placeholders(OrderStatus::activeValues())
            ), OrderStatus::activeValues())
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_orders_count', [OrderStatus::COMPLETED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_orders_count', [OrderStatus::PENDING])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress_orders_count', [OrderStatus::IN_PROGRESS])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as paused_orders_count', [OrderStatus::PAUSED])
            ->selectRaw("COALESCE(SUM(CASE WHEN status = ? THEN {$payoutExpression} ELSE 0 END), 0) as estimated_lifetime_earnings_cents", [OrderStatus::COMPLETED])
            ->selectRaw(sprintf(
                "COALESCE(SUM(CASE WHEN status IN (%s) THEN {$payoutExpression} ELSE 0 END), 0) as estimated_pending_earnings_cents",
                $this->placeholders(OrderStatus::activeValues())
            ), OrderStatus::activeValues())
            ->selectRaw("COALESCE(ROUND(AVG({$payoutBasisExpression})), 0) as average_order_value_cents")
            ->first();

        $activeOrders = Order::query()
            ->select(['id', 'user_id', 'order_number', 'product', 'status', 'details', 'created_at'])
            ->with('user:id,name,nickname,email')
            ->where('booster_id', $user->id)
            ->whereIn('status', OrderStatus::activeValues())
            ->latest('created_at')
            ->limit(5)
            ->get();

        $recentCompletedOrders = Order::query()
            ->select(['id', 'user_id', 'order_number', 'product', 'details', 'price_cents', 'original_price_cents', 'discount_amount', 'currency', 'booster_payout_rate', 'booster_payout_cents', 'booster_payout_basis_cents', 'created_at', 'updated_at', 'status'])
            ->with('user:id,name,nickname,email')
            ->where('booster_id', $user->id)
            ->where('status', OrderStatus::COMPLETED)
            ->latest('updated_at')
            ->limit(8)
            ->get();

        return [
            'user' => $user,
            'totalAssignedOrders' => (int) ($summary->total_assigned_orders ?? 0),
            'activeOrdersCount' => (int) ($summary->active_orders_count ?? 0),
            'completedOrdersCount' => (int) ($summary->completed_orders_count ?? 0),
            'pendingOrdersCount' => (int) ($summary->pending_orders_count ?? 0),
            'inProgressOrdersCount' => (int) ($summary->in_progress_orders_count ?? 0),
            'pausedOrdersCount' => (int) ($summary->paused_orders_count ?? 0),
            'estimatedLifetimeEarningsCents' => (int) ($summary->estimated_lifetime_earnings_cents ?? 0),
            'estimatedPendingEarningsCents' => (int) ($summary->estimated_pending_earnings_cents ?? 0),
            'averageOrderValueCents' => (int) ($summary->average_order_value_cents ?? 0),
            'boosterPayoutPercentage' => Order::configuredBoosterPayoutPercentage(),
            'activeOrders' => $activeOrders,
            'recentCompletedOrders' => $recentCompletedOrders,
        ];
    }

    protected function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }
}
