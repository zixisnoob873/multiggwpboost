<?php

use App\Http\Controllers\AuthWebController;
use App\Http\Controllers\OAuthWebController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthWebController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthWebController::class, 'login'])->middleware('throttle:login-route')->name('login.submit');
    Route::get('forgot-password', [AuthWebController::class, 'showForgotPassword'])->name('password.request');
    Route::post('forgot-password', [AuthWebController::class, 'sendPasswordResetLink'])->middleware('throttle:password-reset-link')->name('password.email');
    Route::get('reset-password/{token}', [AuthWebController::class, 'showResetPassword'])->name('password.reset');
    Route::post('reset-password', [AuthWebController::class, 'resetPassword'])->middleware('throttle:password-reset-submit')->name('password.update');
    Route::get('signup', [AuthWebController::class, 'showSignup'])->name('signup');
    Route::post('signup', [AuthWebController::class, 'register'])->middleware('throttle:register-route')->name('signup.submit');
    Route::get('auth/{provider}/redirect', [OAuthWebController::class, 'redirect'])
        ->where('provider', 'google|discord')
        ->middleware('throttle:oauth-route')
        ->name('oauth.redirect');
    Route::get('auth/{provider}/callback', [OAuthWebController::class, 'callback'])
        ->where('provider', 'google|discord')
        ->middleware('throttle:oauth-route')
        ->name('oauth.callback');
    Route::get('auth/complete-profile', [OAuthWebController::class, 'showCompleteProfile'])->name('oauth.complete-profile');
    Route::post('auth/complete-profile', [OAuthWebController::class, 'completeProfile'])
        ->middleware('throttle:oauth-complete-profile')
        ->name('oauth.complete-profile.submit');
});

Route::middleware(['auth', 'password.confirm'])->group(function () {
    Route::get('auth/{provider}/link/redirect', [OAuthWebController::class, 'linkRedirect'])
        ->where('provider', 'google|discord')
        ->middleware('throttle:oauth-route')
        ->name('oauth.link.redirect');
    Route::get('auth/{provider}/link/callback', [OAuthWebController::class, 'linkCallback'])
        ->where('provider', 'google|discord')
        ->middleware('throttle:oauth-route')
        ->name('oauth.link.callback');
});

Route::get('confirm-password', [AuthWebController::class, 'showConfirmPassword'])
    ->middleware('auth')
    ->name('password.confirm');
Route::post('confirm-password', [AuthWebController::class, 'confirmPassword'])
    ->middleware(['auth', 'throttle:login-route'])
    ->name('password.confirm.submit');

Route::post('logout', [AuthWebController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');
