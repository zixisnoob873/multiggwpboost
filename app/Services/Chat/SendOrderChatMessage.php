<?php

namespace App\Services\Chat;

use App\Enums\OrderChatThreadType;
use App\Events\OrderChatMessageSent;
use App\Models\Order;
use App\Models\OrderChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SendOrderChatMessage
{
    public function __construct(protected EnsureOrderChatThreads $ensureOrderChatThreads) {}

    public function execute(Order $order, OrderChatThreadType $threadType, User $sender, string $body): OrderChatMessage
    {
        $thread = $this->ensureOrderChatThreads->thread($order, $threadType);

        /** @var OrderChatMessage $message */
        $message = DB::transaction(function () use ($body, $sender, $thread): OrderChatMessage {
            /** @var OrderChatMessage $createdMessage */
            $createdMessage = $thread->messages()->create([
                'sender_id' => $sender->getKey(),
                'sender_role' => (string) $sender->role,
                'sender_name' => $sender->fullIdentity(),
                'body' => $body,
            ]);

            $createdMessage->loadMissing([
                'sender:id,name,nickname,nickname_normalized,role',
                'thread:id,order_id,thread_type',
            ]);

            DB::afterCommit(function () use ($createdMessage): void {
                broadcast(new OrderChatMessageSent($createdMessage))->toOthers();
            });

            return $createdMessage;
        });

        return $message;
    }
}
