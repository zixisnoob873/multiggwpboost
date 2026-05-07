<?php

namespace App\Services\Chat;

use App\Enums\OrderChatThreadType;
use App\Events\OrderChatMessageSent;
use App\Models\Order;
use App\Models\OrderChatMessage;
use Illuminate\Support\Facades\DB;

class SendOrderSystemMessage
{
    public function __construct(protected EnsureOrderChatThreads $ensureOrderChatThreads) {}

    public function execute(Order $order, OrderChatThreadType $threadType, string $body): OrderChatMessage
    {
        $thread = $this->ensureOrderChatThreads->thread($order, $threadType);

        /** @var OrderChatMessage $message */
        $message = $thread->messages()->create([
            'sender_id' => null,
            'sender_role' => 'system',
            'sender_name' => 'System',
            'body' => $body,
        ]);

        $message->loadMissing([
            'sender:id,name,nickname,nickname_normalized,role',
            'thread:id,order_id,thread_type',
        ]);

        DB::afterCommit(function () use ($message): void {
            broadcast(new OrderChatMessageSent($message));
        });

        return $message;
    }
}
