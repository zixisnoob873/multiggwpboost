<?php

namespace App\Queries;

use App\Models\Order;
use App\Support\OrderStatus;
use App\Models\User;

class CustomerOrdersQuery
{
    public function execute(?User $user): array
    {
        $query = Order::query()
            ->select(['id', 'user_id', 'booster_id', 'order_number', 'product', 'status', 'details', 'price_cents', 'original_price_cents', 'discount_amount', 'currency', 'created_at'])
            ->with('booster:id,name,nickname,email')
            ->latest('created_at');

        if ($user && ! $user->isAdminUser()) {
            $query->where('user_id', $user->id);
        }

        $orders = $query->get();

        return [
            'orders' => $orders,
            'totalOrders' => $orders->count(),
            'completedOrders' => $orders->where('status', OrderStatus::COMPLETED)->count(),
            'activeOrders' => $orders->filter(fn ($order) => OrderStatus::isActive($order->status))->count(),
            'lifetimeSpendCents' => $orders
                ->reject(fn ($order) => in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::REFUNDED], true))
                ->sum('price_cents'),
        ];
    }
}
