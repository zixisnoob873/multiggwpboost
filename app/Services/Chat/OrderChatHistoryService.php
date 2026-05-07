<?php

namespace App\Services\Chat;

use App\Models\OrderChatMessage;
use App\Models\OrderChatThread;

class OrderChatHistoryService
{
    public function load(OrderChatThread $thread, int $limit = 25, ?int $beforeMessageId = null): array
    {
        $safeLimit = max(1, min(50, $limit));

        $query = $thread->messages()
            ->with(['sender:id,name,nickname,nickname_normalized,role', 'thread:id,order_id,thread_type'])
            ->orderByDesc('id');

        if ($beforeMessageId !== null) {
            $query->where('id', '<', $beforeMessageId);
        }

        $messages = $query
            ->limit($safeLimit + 1)
            ->get();

        $hasMore = $messages->count() > $safeLimit;
        $slice = $messages->take($safeLimit)->sortBy('id')->values();

        /** @var OrderChatMessage|null $oldestMessage */
        $oldestMessage = $slice->first();

        return [
            'messages' => $slice,
            'has_more' => $hasMore,
            'next_cursor' => $hasMore && $oldestMessage ? $oldestMessage->getKey() : null,
        ];
    }
}
