<?php

use App\Models\User;
use App\Services\Chat\OrderChatAuthorizationService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('order-chat.{orderId}.{threadType}', function (User $user, int $orderId, string $threadType) {
    return app(OrderChatAuthorizationService::class)->canSubscribe($user, $orderId, $threadType);
});
