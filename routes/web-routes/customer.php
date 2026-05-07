<?php

use App\Http\Controllers\AuthWebController;
use App\Http\Controllers\ChatRouteRedirectController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerOrderActionController;
use Illuminate\Support\Facades\Route;

Route::prefix('user')->middleware(['auth', 'role:customer-super_admin-booster'])->group(function () {
    Route::get('chats', [CustomerController::class, 'chats'])->name('user-chats');
    Route::redirect('chat', '/user/chats', 301);
    Route::get('chat/{order:order_number}', [ChatRouteRedirectController::class, 'user']);
    Route::get('chats/{order:order_number}', [CustomerController::class, 'showChat'])->name('user-chats.show');
    Route::get('my-order', [CustomerController::class, 'myOrder'])->name('my-order');
});

Route::prefix('user')->middleware(['auth', 'role:customer'])->group(function () {
    Route::get('dashboard', [CustomerController::class, 'dashboard'])->name('customer-dashboard');
    Route::post('password', [AuthWebController::class, 'updatePassword'])->name('user.password.update');
    Route::post('profile-photo', [AuthWebController::class, 'updateProfilePhoto'])->name('user.profile-photo.update');
    Route::get('upgrade-order', [CustomerController::class, 'upgradeOrder'])->name('customer-upgrade-order');
    Route::get('orders', [CustomerController::class, 'allOrders'])->name('allorders');
    Route::post('checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout-submit')->name('checkout.submit');
    Route::post('orders/{order}/extend', [CustomerOrderActionController::class, 'startExtensionCheckout'])
        ->middleware('throttle:checkout-submit')
        ->name('customer-orders.extend.checkout');
    Route::post('orders/{order}/pause', [CustomerOrderActionController::class, 'pause'])
        ->middleware('throttle:customer-order-actions')
        ->name('customer-orders.pause');
    Route::post('orders/{order}/resume', [CustomerOrderActionController::class, 'resume'])
        ->middleware('throttle:customer-order-actions')
        ->name('customer-orders.resume');
    Route::post('orders/{order}/tips/booster', [CustomerOrderActionController::class, 'startBoosterTipCheckout'])
        ->middleware('throttle:checkout-submit')
        ->name('customer-orders.tips.booster.checkout');
    Route::post('orders/{order}/tips/admin', [CustomerOrderActionController::class, 'startAdminTipCheckout'])
        ->middleware('throttle:checkout-submit')
        ->name('customer-orders.tips.admin.checkout');
});
