<?php

namespace App\Queries\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class BoosterIndexQuery
{
    public function execute(array $filters = []): array
    {
        $search = (string) ($filters['search'] ?? '');
        $sort = (string) ($filters['sort'] ?? 'created_at');
        $direction = (string) ($filters['direction'] ?? 'desc');

        $query = User::query()
            ->where('role', 'booster')
            ->withCount('boosterOrders')
            ->when(($filters['status'] ?? null) !== null, fn (Builder $builder) => $builder->where('account_status', $filters['status']))
            ->when(($filters['created_from'] ?? null) !== null, fn (Builder $builder) => $builder->whereDate('created_at', '>=', $filters['created_from']))
            ->when(($filters['created_to'] ?? null) !== null, fn (Builder $builder) => $builder->whereDate('created_at', '<=', $filters['created_to']))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';

                $builder->where(function (Builder $nested) use ($like): void {
                    $nested
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('nickname', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like);
                });
            });

        $boosters = match ($sort) {
            'nickname' => $query->orderBy('nickname_normalized', $direction)->orderBy('created_at', 'desc'),
            'email' => $query->orderBy('email', $direction),
            'booster_orders_count' => $query->orderBy('booster_orders_count', $direction),
            default => $query->orderBy('created_at', $direction),
        };
        $boosters = $boosters
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->withQueryString();

        return [
            'boosters' => $boosters,
            'boosterFilters' => $filters,
            'boostersCount' => User::query()->where('role', 'booster')->count(),
        ];
    }
}
