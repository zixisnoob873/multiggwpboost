<?php

namespace App\Queries\Admin;

use App\Models\BoosterApplication;
use Illuminate\Database\Eloquent\Builder;

class BoosterApplicationsQuery
{
    public function execute(array $filters = []): array
    {
        $search = (string) ($filters['search'] ?? '');
        $sort = (string) ($filters['sort'] ?? 'created_at');
        $direction = (string) ($filters['direction'] ?? 'desc');

        $query = BoosterApplication::query()
            ->with(['reviewer:id,name,email', 'convertedBooster:id,name,nickname,email'])
            ->when(($filters['status'] ?? null) !== null, fn (Builder $builder) => $builder->where('status', $filters['status']))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';

                $builder->where(function (Builder $nested) use ($like): void {
                    $nested
                        ->where('name', 'like', $like)
                        ->orWhere('nickname', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('discord', 'like', $like)
                        ->orWhere('current_rank', 'like', $like)
                        ->orWhere('peak_rank', 'like', $like);
                });
            });

        $applications = match ($sort) {
            'name' => $query->orderBy('name', $direction),
            'status' => $query->orderBy('status', $direction)->orderBy('created_at', 'desc'),
            'peak_rank' => $query->orderBy('peak_rank', $direction),
            default => $query->orderBy('created_at', $direction),
        };

        return [
            'applications' => $applications
                ->paginate((int) ($filters['per_page'] ?? 20))
                ->withQueryString(),
            'applicationFilters' => $filters,
            'applicationStats' => collect(BoosterApplication::statusOptions())
                ->mapWithKeys(fn (string $label, string $status): array => [
                    $status => BoosterApplication::query()->where('status', $status)->count(),
                ])
                ->all(),
        ];
    }
}
