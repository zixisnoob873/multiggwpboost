<?php

namespace App\Queries\Admin;

use App\Models\Order;
use App\Support\OrderStatus;
use Illuminate\Database\Eloquent\Builder;

class OrderIndexQuery
{
    public function paginate(array $filters): array
    {
        $query = $this->baseQuery($filters);
        $orders = $query
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();

        return [
            'orders' => $orders,
            'orderFilters' => $filters,
            'orderTabs' => $this->tabCounts(),
        ];
    }

    public function export(array $filters)
    {
        return $this->baseQuery($filters)->get();
    }

    protected function baseQuery(array $filters): Builder
    {
        $search = (string) ($filters['search'] ?? '');
        $sort = (string) ($filters['sort'] ?? 'created_at');
        $direction = (string) ($filters['direction'] ?? 'desc');

        $query = Order::query()
            ->with([
                'user:id,name,first_name,last_name,nickname,email',
                'booster:id,name,first_name,last_name,nickname,email',
                'promoCode:id,code',
                'game:id,slug,name,short_name',
                'gameService:id,game_id,slug,name,kind',
            ])
            ->select('orders.*')
            ->withCount('chatThreads')
            ->when($filters['tab'] === 'needs_assignment', fn (Builder $builder) => $builder
                ->whereNull('booster_id')
                ->whereIn('status', OrderStatus::activeValues()))
            ->when($filters['tab'] === 'in_progress', fn (Builder $builder) => $builder->where('status', OrderStatus::IN_PROGRESS))
            ->when($filters['tab'] === 'paused', fn (Builder $builder) => $builder->where('status', OrderStatus::PAUSED))
            ->when($filters['tab'] === 'completed', fn (Builder $builder) => $builder->where('status', OrderStatus::COMPLETED))
            ->when($filters['tab'] === 'manual', fn (Builder $builder) => $builder->where('is_custom', true))
            ->when(($filters['status'] ?? null) !== null, fn (Builder $builder) => $builder->where('status', $filters['status']))
            ->when(($filters['payment_status'] ?? null) !== null, fn (Builder $builder) => $builder->where('payment_status', $filters['payment_status']))
            ->when(($filters['assignment'] ?? 'any') === 'assigned', fn (Builder $builder) => $builder->whereNotNull('booster_id'))
            ->when(($filters['assignment'] ?? 'any') === 'unassigned', fn (Builder $builder) => $builder->whereNull('booster_id'))
            ->when(($filters['customer_id'] ?? null) !== null, fn (Builder $builder) => $builder->where('user_id', $filters['customer_id']))
            ->when(($filters['booster_id'] ?? null) !== null, fn (Builder $builder) => $builder->where('booster_id', $filters['booster_id']))
            ->when(($filters['created_from'] ?? null) !== null, fn (Builder $builder) => $builder->whereDate('created_at', '>=', $filters['created_from']))
            ->when(($filters['created_to'] ?? null) !== null, fn (Builder $builder) => $builder->whereDate('created_at', '<=', $filters['created_to']))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';

                $builder->where(function (Builder $nested) use ($like): void {
                    $nested
                        ->where('order_number', 'like', $like)
                        ->orWhere('product', 'like', $like)
                        ->orWhereHas('user', fn (Builder $user) => $user
                            ->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('nickname', 'like', $like))
                        ->orWhereHas('booster', fn (Builder $booster) => $booster
                            ->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('nickname', 'like', $like))
                        ->orWhereHas('promoCode', fn (Builder $promoCode) => $promoCode->where('code', 'like', $like))
                        ->orWhereHas('game', fn (Builder $game) => $game
                            ->where('name', 'like', $like)
                            ->orWhere('slug', 'like', $like))
                        ->orWhereHas('gameService', fn (Builder $service) => $service
                            ->where('name', 'like', $like)
                            ->orWhere('slug', 'like', $like));
                });
            });

        return match ($sort) {
            'order_number' => $query->orderBy('order_number', $direction),
            'price_cents' => $query->orderBy('price_cents', $direction),
            'status' => $query->orderBy('status', $direction)->orderBy('created_at', 'desc'),
            'assigned_at' => $query->orderBy('assigned_at', $direction)->orderBy('created_at', 'desc'),
            default => $query->orderBy('created_at', $direction),
        };
    }

    protected function tabCounts(): array
    {
        $summary = Order::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN booster_id IS NULL AND status IN (?, ?, ?) THEN 1 ELSE 0 END) as needs_assignment', OrderStatus::activeValues())
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress', [OrderStatus::IN_PROGRESS])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as paused', [OrderStatus::PAUSED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed', [OrderStatus::COMPLETED])
            ->selectRaw('SUM(CASE WHEN is_custom = 1 THEN 1 ELSE 0 END) as manual')
            ->first();

        return [
            'all' => (int) ($summary->total ?? 0),
            'needs_assignment' => (int) ($summary->needs_assignment ?? 0),
            'in_progress' => (int) ($summary->in_progress ?? 0),
            'paused' => (int) ($summary->paused ?? 0),
            'completed' => (int) ($summary->completed ?? 0),
            'manual' => (int) ($summary->manual ?? 0),
        ];
    }
}
