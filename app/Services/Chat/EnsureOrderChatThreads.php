<?php

namespace App\Services\Chat;

use App\Enums\OrderChatThreadType;
use App\Models\Order;
use App\Models\OrderChatThread;
use Illuminate\Support\Collection;

class EnsureOrderChatThreads
{
    public function execute(Order $order): Collection
    {
        $existing = $order->chatThreads()
            ->get()
            ->keyBy(static fn (OrderChatThread $thread) => $thread->thread_type->value);

        $missingRows = [];
        $timestamp = now();

        foreach (OrderChatThreadType::cases() as $threadType) {
            if ($existing->has($threadType->value)) {
                continue;
            }

            $missingRows[] = [
                'order_id' => $order->getKey(),
                'thread_type' => $threadType->value,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        if ($missingRows !== []) {
            OrderChatThread::query()->upsert(
                $missingRows,
                ['order_id', 'thread_type'],
                ['updated_at']
            );
        }

        return $order->chatThreads()
            ->get()
            ->keyBy(static fn (OrderChatThread $thread) => $thread->thread_type->value);
    }

    public function thread(Order $order, OrderChatThreadType $threadType): OrderChatThread
    {
        /** @var OrderChatThread $thread */
        $thread = $this->execute($order)->get($threadType->value);

        return $thread;
    }
}
