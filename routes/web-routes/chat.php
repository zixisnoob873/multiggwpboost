<?php

use App\Http\Controllers\OrderChatMessageController;
use App\Http\Controllers\OrderProgressController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::patch('orders/{order}/progress', [OrderProgressController::class, 'update'])
        ->middleware('throttle:order-progress-update')
        ->name('orders.progress.update');
    Route::get('orders/{order:order_number}/chats/{threadType}/messages', [OrderChatMessageController::class, 'index'])
        ->middleware('throttle:chat-history')
        ->name('order-chat.messages.index');
    Route::post('orders/{order:order_number}/chats/{threadType}/messages', [OrderChatMessageController::class, 'store'])
        ->middleware('throttle:chat-send')
        ->name('order-chat.messages.store');
    Route::get('orders/{order}/chat/{threadType}/messages', [OrderChatMessageController::class, 'index'])
        ->middleware('throttle:chat-history');
    Route::post('orders/{order}/chat/{threadType}/messages', [OrderChatMessageController::class, 'store'])
        ->middleware('throttle:chat-send');
});
