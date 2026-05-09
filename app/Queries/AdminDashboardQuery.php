<?php

namespace App\Queries;

use App\Models\BoosterApplication;
use App\Models\ContactMessage;
use App\Models\CustomerOrderEmailDispatch;
use App\Models\DiscordNotificationDispatch;
use App\Models\Order;
use App\Models\OrderChatMessage;
use App\Models\OrderTip;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Support\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminDashboardQuery
{
    public function execute(string $period): array
    {
        $originalPriceExpression = Order::originalPriceSql();
        $payoutExpression = Order::boosterPayoutSql();

        $financeOrdersQuery = Order::query()
            ->when($period === 'current_month', fn (Builder $query) => $query->whereBetween('created_at', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ]));
        $adminTipsCents = 0;

        if (Schema::hasTable('order_tips')) {
            $adminTipsQuery = OrderTip::query()
                ->where('recipient_type', OrderTip::RECIPIENT_ADMIN)
                ->when($period === 'current_month', fn (Builder $query) => $query->whereBetween('created_at', [
                    now()->startOfMonth(),
                    now()->endOfMonth(),
                ]));

            $adminTipsCents = (int) ((clone $adminTipsQuery)->sum('amount_cents') ?? 0);
        }
        $summary = (clone $financeOrdersQuery)
            ->selectRaw('COALESCE(SUM(price_cents), 0) as total_sale_cents')
            ->selectRaw("COALESCE(SUM({$originalPriceExpression}), 0) as total_original_sale_cents")
            ->selectRaw('COALESCE(SUM(ROUND(COALESCE(discount_amount, 0) * 100)), 0) as total_discount_cents')
            ->selectRaw("COALESCE(SUM({$payoutExpression}), 0) as estimated_booster_payouts_cents")
            ->selectRaw(sprintf(
                'SUM(CASE WHEN status IN (%s) THEN 1 ELSE 0 END) as active_orders_count',
                $this->placeholders(OrderStatus::activeValues())
            ), OrderStatus::activeValues())
            ->first();

        $customersQuery = User::query()->where('role', 'customer')->latest('created_at');
        $userCounts = User::query()
            ->selectRaw("SUM(CASE WHEN role = 'booster' THEN 1 ELSE 0 END) as boosters_count")
            ->selectRaw("SUM(CASE WHEN role = 'customer' THEN 1 ELSE 0 END) as customers_count")
            ->first();

        return [
            'totalSaleCents' => (int) ($summary->total_sale_cents ?? 0),
            'totalOriginalSaleCents' => (int) ($summary->total_original_sale_cents ?? 0),
            'totalDiscountCents' => (int) ($summary->total_discount_cents ?? 0),
            'estimatedBoosterPayoutsCents' => (int) ($summary->estimated_booster_payouts_cents ?? 0),
            'adminTipsCents' => $adminTipsCents,
            'activeOrders' => (int) ($summary->active_orders_count ?? 0),
            'recentOrders' => Order::query()
                ->select(['id', 'user_id', 'booster_id', 'order_number', 'product', 'status', 'details', 'price_cents', 'original_price_cents', 'discount_amount', 'booster_payout_basis_cents', 'created_at'])
                ->with([
                    'user:id,name,email',
                    'booster:id,name,email',
                ])
                ->latest('created_at')
                ->limit(5)
                ->get(),
            'customers' => (clone $customersQuery)
                ->select(['id', 'name', 'email', 'created_at'])
                ->limit(5)
                ->get(),
            'boostersCount' => (int) ($userCounts->boosters_count ?? 0),
            'customersCount' => (int) ($userCounts->customers_count ?? 0),
            'boosterApplicationsCount' => BoosterApplication::count(),
            'withdrawalRequestsCount' => WithdrawalRequest::where('status', 'pending')->count(),
            'operationalHealth' => [
                'active_orders' => (int) ($summary->active_orders_count ?? 0),
                'needs_assignment' => Order::query()->whereNull('booster_id')->whereIn('status', OrderStatus::activeValues())->count(),
                'paused_orders' => Order::query()->where('status', OrderStatus::PAUSED)->count(),
                'pending_withdrawals' => WithdrawalRequest::query()->where('status', WithdrawalRequest::STATUS_PENDING)->count(),
                'unread_contact_messages' => ContactMessage::query()->where('status', ContactMessage::STATUS_NEW)->count(),
                'new_booster_applications' => BoosterApplication::query()->where('status', 'new')->count(),
            ],
            'ordersNeedingAction' => Order::query()
                ->with(['user:id,name,email', 'booster:id,name,email'])
                ->where(function (Builder $builder): void {
                    $builder
                        ->where(function (Builder $nested): void {
                            $nested->whereNull('booster_id')->whereIn('status', OrderStatus::activeValues());
                        })
                        ->orWhere('status', OrderStatus::PAUSED);
                })
                ->latest('created_at')
                ->limit(5)
                ->get(),
            'openChatsNeedingReply' => Order::query()
                ->with(['user:id,name,email', 'booster:id,name,email'])
                ->whereHas('chatThreads.messages')
                ->select('orders.*')
                ->selectSub($this->latestMessageSubquery('order_chat_messages.created_at'), 'latest_chat_at')
                ->selectSub($this->latestMessageSubquery('order_chat_messages.sender_role'), 'latest_sender_role')
                ->whereRaw(
                    'COALESCE(('.$this->latestMessageSubquery('order_chat_messages.sender_role')->toSql()."), '') <> ?",
                    array_merge($this->latestMessageSubquery('order_chat_messages.sender_role')->getBindings(), [User::ROLE_SUPER_ADMIN])
                )
                ->orderByDesc('latest_chat_at')
                ->limit(5)
                ->get(),
            'pendingWithdrawals' => WithdrawalRequest::query()
                ->with('booster:id,name,email,nickname')
                ->where('status', WithdrawalRequest::STATUS_PENDING)
                ->latest('created_at')
                ->limit(5)
                ->get(),
            'newBoosterApplications' => BoosterApplication::query()
                ->where('status', 'new')
                ->latest('created_at')
                ->limit(5)
                ->get(),
            'newInboxMessages' => ContactMessage::query()
                ->with(['assignedAdmin:id,name'])
                ->whereIn('status', ContactMessage::activeStatuses())
                ->latest('created_at')
                ->limit(5)
                ->get(),
            'systemHealth' => [
                'jobs_pending' => Schema::hasTable('jobs') ? (int) DB::table('jobs')->count() : null,
                'failed_jobs' => Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : null,
                'failed_discord_dispatches' => Schema::hasTable('discord_notification_dispatches')
                    ? DiscordNotificationDispatch::query()->where('status', DiscordNotificationDispatch::STATUS_FAILED)->count()
                    : null,
                'failed_customer_emails' => Schema::hasTable('customer_order_email_dispatches')
                    ? CustomerOrderEmailDispatch::query()->where('status', CustomerOrderEmailDispatch::STATUS_FAILED)->count()
                    : null,
            ],
        ];
    }

    protected function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }

    protected function latestMessageSubquery(string $column)
    {
        return OrderChatMessage::query()
            ->selectRaw($column)
            ->join('order_chat_threads', 'order_chat_threads.id', '=', 'order_chat_messages.order_chat_thread_id')
            ->whereColumn('order_chat_threads.order_id', 'orders.id')
            ->latest('order_chat_messages.created_at')
            ->limit(1);
    }
}
