<?php

namespace App\Queries;

use App\Models\Order;
use App\Models\User;
use App\Support\OrderStatus;

class CustomerDashboardQuery
{
    public function execute(User $user): array
    {
        $excludedSpendStatuses = [OrderStatus::CANCELLED, OrderStatus::REFUNDED];

        $summary = Order::query()
            ->where('user_id', $user->id)
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw(sprintf(
                'SUM(CASE WHEN status IN (%s) THEN 1 ELSE 0 END) as active_orders_count',
                $this->placeholders(OrderStatus::activeValues())
            ), OrderStatus::activeValues())
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_orders_count', [OrderStatus::COMPLETED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as paused_orders_count', [OrderStatus::PAUSED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as cancelled_orders_count', [OrderStatus::CANCELLED])
            ->selectRaw(sprintf(
                'COALESCE(SUM(CASE WHEN status NOT IN (%s) THEN price_cents ELSE 0 END), 0) as lifetime_spend_cents',
                $this->placeholders($excludedSpendStatuses)
            ), $excludedSpendStatuses)
            ->first();

        $activeOrders = Order::query()
            ->select(['id', 'booster_id', 'order_number', 'product', 'status', 'details', 'created_at'])
            ->with('booster:id,name,nickname,email')
            ->where('user_id', $user->id)
            ->whereIn('status', OrderStatus::activeValues())
            ->latest('created_at')
            ->limit(5)
            ->get();

        $recentOrders = Order::query()
            ->select(['id', 'booster_id', 'order_number', 'product', 'status', 'details', 'price_cents', 'original_price_cents', 'discount_amount', 'currency', 'created_at'])
            ->with('booster:id,name,nickname,email')
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->limit(8)
            ->get();

        return [
            'user' => $user,
            'totalOrders' => (int) ($summary->total_orders ?? 0),
            'activeOrdersCount' => (int) ($summary->active_orders_count ?? 0),
            'completedOrdersCount' => (int) ($summary->completed_orders_count ?? 0),
            'pausedOrdersCount' => (int) ($summary->paused_orders_count ?? 0),
            'cancelledOrdersCount' => (int) ($summary->cancelled_orders_count ?? 0),
            'lifetimeSpendCents' => (int) ($summary->lifetime_spend_cents ?? 0),
            'activeOrders' => $activeOrders,
            'recentOrders' => $recentOrders,
        ];
    }

    protected function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }
}
