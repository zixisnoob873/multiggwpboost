<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\BoosterApplicationController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CheckoutPageController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CryptomusWebhookController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\HealthReadinessController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MaintenancePageController;
use App\Http\Controllers\OrderPaymentController;
use App\Http\Controllers\PriceCalculationController;
use App\Http\Controllers\PricingConfigController;
use App\Http\Controllers\ProfilePhotoController;
use App\Http\Controllers\PromotionImageController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'home'])->name('home');
Route::get('services/{category}', [HomeController::class, 'serviceCategory'])->name('services.categories.show');
Route::get('game/{game}/{service}', [HomeController::class, 'gameService'])->name('game.services.show');
Route::get('game/{game}', [HomeController::class, 'gameLanding'])->name('game.show');
Route::get('games/{game}/pricing-config', PricingConfigController::class)->middleware('throttle:public-api-read')->name('games.pricing.config');
Route::get('games/category/{category}', [HomeController::class, 'gameCategory'])->name('games.categories.show');
Route::get('games/{game}', fn (string $game) => redirect()->route('game.show', ['game' => $game], 301))->name('games.show');
Route::get('games/{game}/{service}', fn (string $game, string $service) => redirect()->route('game.services.show', [
    'game' => $game,
    'service' => $service,
], 301))->name('games.services.show');
Route::get('under_maintenance', MaintenancePageController::class)->name('under-maintenance');
Route::get('sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('robots.txt', RobotsController::class)->name('robots');
Route::get('ready', HealthReadinessController::class)->middleware('throttle:health-readiness')->name('health.ready');
Route::get('internal/ready', [HealthReadinessController::class, 'internal'])
    ->middleware(['signed', 'throttle:health-readiness'])
    ->name('health.ready.internal');
Route::get('profile-photos/{user}', ProfilePhotoController::class)
    ->middleware('signed')
    ->name('profile-photos.show');
Route::get('promotion-images/{promotion}', PromotionImageController::class)
    ->middleware('signed')
    ->name('promotion-images.show');
Route::post('calculate-price', PriceCalculationController::class)->middleware('throttle:pricing-calculate')->name('pricing.calculate');
Route::get('pricing-config', PricingConfigController::class)->middleware('throttle:public-api-read')->name('pricing.config');
Route::post('checkout/promo-code/preview', [CheckoutController::class, 'previewPromoCode'])->middleware('throttle:promo-preview')->name('checkout.promo.preview');
Route::post('stripe/webhook', StripeWebhookController::class)
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->middleware('throttle:stripe-webhook')
    ->name('stripe.webhook');
Route::post('cryptomus/webhook', CryptomusWebhookController::class)
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->middleware('throttle:cryptomus-webhook')
    ->name('cryptomus.webhook');
Route::get('api/faqs', [FaqController::class, 'index'])->middleware('throttle:public-api-read')->name('api.faqs');
Route::get('become-booster', [BoosterApplicationController::class, 'create'])->name('become-booster');
Route::post('become-booster', [BoosterApplicationController::class, 'store'])->middleware('throttle:booster-application')->name('become-booster.submit');
Route::get('blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('blog/category/{category}', [BlogController::class, 'category'])->name('blog.category');
Route::get('blog/tag/{tag}', [BlogController::class, 'tag'])->name('blog.tag');
Route::get('blog/{slug}', [BlogController::class, 'show'])->name('blog.show');
Route::get('checkout', [CheckoutPageController::class, 'show'])->name('checkout');
Route::get('code-of-ethics', [CheckoutPageController::class, 'codeOfEthics'])->name('code-of-ethics');
Route::get('contact', [ContactController::class, 'contact'])->name('contact');
Route::post('contact', [ContactController::class, 'submit'])->middleware('throttle:contact-form')->name('contact.submit');
Route::get('faq', [HomeController::class, 'faq'])->name('faq');
Route::get('privacy-policy', [CheckoutPageController::class, 'privacyPolicy'])->name('privacy-policy');
Route::get('refund-policy', [CheckoutPageController::class, 'refundPolicy'])->name('refund-policy');
Route::get('reviews', [CheckoutPageController::class, 'reviews'])->name('reviews');
Route::get('terms-and-conditions', [CheckoutPageController::class, 'termsAndConditions'])->name('terms-and-conditions');
Route::get('orders/success', [OrderPaymentController::class, 'success'])->middleware('auth')->name('orders.success');
