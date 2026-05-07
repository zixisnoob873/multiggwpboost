<?php

use App\Http\Controllers\AuthWebController;
use App\Http\Controllers\BoosterController;
use App\Http\Controllers\ChatRouteRedirectController;
use Illuminate\Support\Facades\Route;

Route::prefix('boost')->middleware(['auth', 'role:booster'])->group(function () {
    Route::redirect('chat', '/booster/chats/all', 301);
    Route::redirect('chats', '/booster/chats/all', 301);
    Route::get('chat/{order:order_number}', [ChatRouteRedirectController::class, 'booster']);
    Route::get('chats/{order:order_number}', [ChatRouteRedirectController::class, 'booster']);
});

Route::prefix('booster')->middleware(['auth', 'role:booster'])->group(function () {
    Route::get('chats/all', [BoosterController::class, 'chats'])->name('booster-chats');
    Route::redirect('chats', '/booster/chats/all', 301);
    Route::redirect('chat', '/booster/chats/all', 301);
    Route::get('chat/{order:order_number}', [ChatRouteRedirectController::class, 'booster']);
    Route::get('chats/{order:order_number}', [BoosterController::class, 'showChat'])->name('booster-chats.show');
    Route::get('claim-orders', [BoosterController::class, 'claimOrders'])->name('booster-claim-orders');
    Route::post('claim-orders/{order}', [BoosterController::class, 'claimOrder'])->name('booster-claim-orders.claim');
    Route::get('dashboard', [BoosterController::class, 'dashboard'])->name('booster-dashboard');
    Route::get('orders', [BoosterController::class, 'orders'])->name('booster-orders');
    Route::patch('orders/{order}/status', [BoosterController::class, 'updateOrderStatus'])->name('booster-orders.status');
    Route::post('orders/{order}/drop', [BoosterController::class, 'dropOrder'])->name('booster-orders.drop');
    Route::post('orders/{order}/completion-proof', [BoosterController::class, 'storeCompletionProof'])->name('booster-orders.completion-proof.store');
    Route::post('orders/{order}/complete', [BoosterController::class, 'completeOrder'])->name('booster-orders.complete');
    Route::post('password', [AuthWebController::class, 'updatePassword'])->name('booster.password.update');
    Route::post('profile-photo', [AuthWebController::class, 'updateProfilePhoto'])->name('booster.profile-photo.update');
    Route::get('wallet', [BoosterController::class, 'wallet'])->name('booster-wallet');
    Route::post('wallet/withdraw', [BoosterController::class, 'submitWithdrawalRequest'])->middleware('throttle:booster-withdrawal')->name('booster-wallet.withdraw');
});
