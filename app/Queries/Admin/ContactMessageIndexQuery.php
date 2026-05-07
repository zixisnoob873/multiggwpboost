<?php

namespace App\Queries\Admin;

use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Builder;

class ContactMessageIndexQuery
{
    public function execute(array $filters = []): array
    {
        $search = (string) ($filters['search'] ?? '');
        $sort = (string) ($filters['sort'] ?? 'created_at');
        $direction = (string) ($filters['direction'] ?? 'desc');

        $query = ContactMessage::query()
            ->with([
                'assignedAdmin:id,name,email',
                'relatedOrder:id,order_number,status',
                'relatedCustomer:id,name,nickname,email',
            ])
            ->when(($filters['status'] ?? null) !== null, fn (Builder $builder) => $builder->where('status', $filters['status']))
            ->when(($filters['assigned_admin_id'] ?? null) !== null, fn (Builder $builder) => $builder->where('assigned_admin_id', $filters['assigned_admin_id']))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';

                $builder->where(function (Builder $nested) use ($like): void {
                    $nested
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('order_ref', 'like', $like)
                        ->orWhere('message', 'like', $like)
                        ->orWhereHas('relatedOrder', fn (Builder $order) => $order->where('order_number', 'like', $like))
                        ->orWhereHas('relatedCustomer', fn (Builder $customer) => $customer
                            ->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('nickname', 'like', $like));
                });
            });

        $messages = match ($sort) {
            'status' => $query->orderBy('status', $direction)->orderBy('created_at', 'desc'),
            'name' => $query->orderBy('name', $direction),
            'email' => $query->orderBy('email', $direction),
            default => $query->orderBy('created_at', $direction),
        };

        return [
            'messages' => $messages
                ->paginate((int) ($filters['per_page'] ?? 25))
                ->withQueryString(),
            'contactFilters' => $filters,
            'contactStats' => collect(ContactMessage::statusOptions())
                ->mapWithKeys(fn (string $label, string $status): array => [
                    $status => ContactMessage::query()->where('status', $status)->count(),
                ])
                ->all(),
        ];
    }
}
