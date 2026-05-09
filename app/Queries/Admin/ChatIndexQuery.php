<?php

namespace App\Queries\Admin;

use App\Models\Order;
use App\Models\OrderChatMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ChatIndexQuery
{
    public function execute(array $filters): array
    {
        $search = (string) ($filters['search'] ?? '');
        $sort = (string) ($filters['sort'] ?? 'latest_activity');
        $direction = (string) ($filters['direction'] ?? 'desc');
        $status = $this->normalizedFilter($filters['status'] ?? null);
        $lane = $this->normalizedFilter($filters['lane'] ?? null);

        $orders = Order::query()
            ->with([
                'user:id,name,nickname,email',
                'booster:id,name,nickname,email',
            ])
            ->whereHas('chatThreads.messages')
            ->select('orders.*')
            ->selectSub($this->latestMessageSubquery('order_chat_messages.created_at'), 'latest_chat_at')
            ->selectSub($this->latestMessageSubquery('order_chat_messages.sender_role'), 'latest_sender_role')
            ->selectSub($this->latestMessageSubquery('order_chat_threads.thread_type'), 'latest_chat_thread_type')
            ->when($status !== null, fn (Builder $builder) => $builder->where('status', $status))
            ->when($lane !== null, fn (Builder $builder) => $builder->whereHas('chatThreads', fn (Builder $thread) => $thread->where('thread_type', $lane)))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';

                $builder->where(function (Builder $nested) use ($like): void {
                    $nested
                        ->where('order_number', 'like', $like)
                        ->orWhereHas('user', fn (Builder $user) => $user
                            ->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('nickname', 'like', $like))
                        ->orWhereHas('booster', fn (Builder $booster) => $booster
                            ->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('nickname', 'like', $like));
                });
            })
            ->when(($filters['reply_state'] ?? 'all') === 'needs_reply', fn (Builder $builder) => $this->applyNeedsReplyConstraint($builder))
            ->when(($filters['reply_state'] ?? 'all') === 'stale', fn (Builder $builder) => $this->applyStaleConstraint($builder))
            ->when($sort === 'created_at', fn (Builder $builder) => $builder->orderBy('created_at', $direction))
            ->when($sort === 'order_number', fn (Builder $builder) => $builder->orderBy('order_number', $direction))
            ->when($sort === 'latest_activity', fn (Builder $builder) => $builder->orderBy('latest_chat_at', $direction))
            ->paginate((int) ($filters['per_page'] ?? 18))
            ->withQueryString();

        return [
            'orders' => $orders,
            'chatFilters' => $filters,
            'chatStats' => [
                'all' => $this->countAllChats(),
                'needs_reply' => $this->countNeedsReply(),
                'stale' => $this->countStaleChats(),
            ],
        ];
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

    protected function countAllChats(): int
    {
        return Order::query()
            ->whereHas('chatThreads.messages')
            ->count();
    }

    protected function countNeedsReply(): int
    {
        $query = Order::query()
            ->whereHas('chatThreads.messages')
            ->select('orders.id');

        $this->applyNeedsReplyConstraint($query);

        return $query->count();
    }

    protected function countStaleChats(): int
    {
        $query = Order::query()
            ->whereHas('chatThreads.messages')
            ->select('orders.id');

        $this->applyStaleConstraint($query);

        return $query->count();
    }

    protected function applyNeedsReplyConstraint(Builder $builder): Builder
    {
        $subquery = $this->latestMessageSubquery('order_chat_messages.sender_role');

        return $builder->whereRaw(
            'COALESCE(('.$subquery->toSql()."), '') <> ?",
            array_merge($subquery->getBindings(), [User::ROLE_SUPER_ADMIN])
        );
    }

    protected function applyStaleConstraint(Builder $builder): Builder
    {
        $subquery = $this->latestMessageSubquery('order_chat_messages.created_at');

        return $builder->whereRaw(
            '('.$subquery->toSql().') <= ?',
            array_merge($subquery->getBindings(), [now()->subHours(12)])
        );
    }

    protected function normalizedFilter(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
