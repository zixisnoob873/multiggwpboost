<?php

namespace App\Queries\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PeopleDirectoryQuery
{
    public function execute(string $role, array $filters = []): array
    {
        $isBooster = $role === 'booster';
        $countRelation = $isBooster ? 'boosterOrders' : 'orders';
        $query = User::query()
            ->where('role', $role)
            ->withCount($countRelation);

        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;
        $createdFrom = $filters['created_from'] ?? null;
        $createdTo = $filters['created_to'] ?? null;
        $sort = $filters['sort'] ?? 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $perPage = max(10, min(100, (int) ($filters['per_page'] ?? 20)));
        $sortColumn = match ($sort) {
            'name' => 'name',
            'email' => 'email',
            'account_status' => 'account_status',
            default => 'created_at',
        };

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nickname', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $query
            ->when($status, fn (Builder $builder) => $builder->where('account_status', $status))
            ->when($createdFrom, fn (Builder $builder) => $builder->whereDate('created_at', '>=', $createdFrom))
            ->when($createdTo, fn (Builder $builder) => $builder->whereDate('created_at', '<=', $createdTo))
            ->orderBy($sortColumn, $direction)
            ->when($sortColumn !== 'created_at', fn (Builder $builder) => $builder->orderByDesc('created_at'));

        $records = $query->paginate($perPage)->withQueryString();

        return [
            'records' => $records,
            'directoryRole' => $role,
            'directoryStats' => [
                'total' => User::query()->where('role', $role)->count(),
                'active' => User::query()->where('role', $role)->where('account_status', 'active')->count(),
                'suspended' => User::query()->where('role', $role)->where('account_status', 'suspended')->count(),
            ],
        ];
    }
}
