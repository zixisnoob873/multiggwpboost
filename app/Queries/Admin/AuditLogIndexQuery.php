<?php

namespace App\Queries\Admin;

use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Builder;

class AuditLogIndexQuery
{
    public function execute(array $filters = []): array
    {
        $search = (string) ($filters['search'] ?? '');
        $sort = (string) ($filters['sort'] ?? 'created_at');
        $direction = (string) ($filters['direction'] ?? 'desc');

        $query = AdminAuditLog::query()
            ->with('actor:id,name,email,role')
            ->when(($filters['module'] ?? null) !== null, fn (Builder $builder) => $builder->where('module', $filters['module']))
            ->when(($filters['actor_id'] ?? null) !== null, fn (Builder $builder) => $builder->where('actor_id', $filters['actor_id']))
            ->when(($filters['action'] ?? null) !== null, fn (Builder $builder) => $builder->where('action', $filters['action']))
            ->when(($filters['created_from'] ?? null) !== null, fn (Builder $builder) => $builder->whereDate('created_at', '>=', $filters['created_from']))
            ->when(($filters['created_to'] ?? null) !== null, fn (Builder $builder) => $builder->whereDate('created_at', '<=', $filters['created_to']))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';

                $builder->where(function (Builder $nested) use ($like): void {
                    $nested
                        ->where('subject_label', 'like', $like)
                        ->orWhere('action', 'like', $like)
                        ->orWhere('route_name', 'like', $like)
                        ->orWhereHas('actor', fn (Builder $actor) => $actor
                            ->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like));
                });
            });

        $logs = match ($sort) {
            'module' => $query->orderBy('module', $direction)->orderBy('created_at', 'desc'),
            'action' => $query->orderBy('action', $direction)->orderBy('created_at', 'desc'),
            default => $query->orderBy('created_at', $direction),
        };

        return [
            'auditLogs' => $logs
                ->paginate((int) ($filters['per_page'] ?? 25))
                ->withQueryString(),
            'auditFilters' => $filters,
        ];
    }
}
