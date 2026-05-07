<?php

namespace App\Events;

use App\Models\OrderChatMessage;
use App\Support\OrderChatMessagePayload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public OrderChatMessage $message)
    {
        $this->message->loadMissing([
            'sender:id,name,nickname,nickname_normalized,role',
            'thread:id,order_id,thread_type',
        ]);
    }

    public function broadcastAs(): string
    {
        return 'order.chat.message.sent';
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(sprintf(
            'order-chat.%d.%s',
            $this->message->thread->order_id,
            $this->message->thread->thread_type->value
        ));
    }

    public function broadcastWith(): array
    {
        return [
            'message' => OrderChatMessagePayload::make($this->message),
        ];
    }
}
