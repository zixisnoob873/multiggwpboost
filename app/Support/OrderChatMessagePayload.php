<?php

namespace App\Support;

use App\Models\OrderChatMessage;
use App\Models\User;

class OrderChatMessagePayload
{
    public static function make(OrderChatMessage $message, ?User $viewer = null): array
    {
        $sender = $message->sender;
        $fallbackName = trim((string) ($message->sender_name ?? '')) ?: 'Deleted User';
        $senderName = $sender?->publicIdentity('Deleted User') ?? $fallbackName;

        return [
            'id' => $message->getKey(),
            'thread_type' => $message->thread?->thread_type?->value,
            'body' => (string) $message->body,
            'sender' => [
                'id' => $message->sender_id,
                'name' => $senderName,
                'role' => $message->sender_role,
            ],
            'is_own' => $viewer?->getKey() !== null && (int) $viewer->getKey() === (int) $message->sender_id,
            'created_at' => optional($message->created_at)?->toIso8601String(),
            'created_at_label' => optional($message->created_at)?->format('M j, Y g:i A'),
        ];
    }
}
