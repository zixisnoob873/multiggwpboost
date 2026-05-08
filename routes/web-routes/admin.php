<?php

use App\Http\Controllers\Admin\AdminBlogArticleController;
use App\Http\Controllers\Admin\AdminBoosterController;
use App\Http\Controllers\Admin\AdminContactMessageController;
use App\Http\Controllers\Admin\AdminContentController;
use App\Http\Controllers\Admin\AdminCustomerController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminFinanceController;
use App\Http\Controllers\Admin\AdminMaintenanceModeController;
use App\Http\Controllers\Admin\AdminMarketplaceAddonController;
use App\Http\Controllers\Admin\AdminMarketplaceGameController;
use App\Http\Controllers\Admin\AdminMarketplaceServiceController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminPageController;
use App\Http\Controllers\Admin\AdminPricingController;
use App\Http\Controllers\Admin\AdminPromoCodeController;
use App\Http\Controllers\Admin\AdminPromotionController;
use App\Http\Controllers\Admin\AdminReviewController;
use App\Http\Controllers\Admin\AdminSystemController;
use App\Http\Controllers\AuthWebController;
use App\Http\Controllers\ChatRouteRedirectController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth', 'admin'])->group(function () {
    Route::redirect('/', '/admin/dashboard');
    Route::get('dashboard', [AdminDashboardController::class, 'dashboard'])->middleware('admin:dashboard')->name('admin-dashboard');
    Route::get('finance', [AdminFinanceController::class, 'index'])->middleware('admin:finance')->name('admin-finance.index');
    Route::get('system/maintenance-mode', [AdminMaintenanceModeController::class, 'index'])->middleware('admin:system')->name('admin-system.maintenance.index');
    Route::get('system/settings', [AdminSystemController::class, 'settings'])->middleware('admin:system')->name('admin-system.settings');
    Route::put('system/settings', [AdminSystemController::class, 'updateSettings'])->middleware('admin:system')->name('admin-system.settings.update');
    Route::get('system/audit-logs', [AdminSystemController::class, 'auditLogs'])->middleware('admin:system')->name('admin-system.audit-logs');
    Route::get('pricing', [AdminPricingController::class, 'index'])->middleware('admin:system,system.pricing.view')->name('admin-pricing.index');
    Route::put('pricing', [AdminPricingController::class, 'update'])->middleware('admin:system,system.pricing.manage')->name('admin-pricing.update');
    Route::post('pricing/reset', [AdminPricingController::class, 'reset'])->middleware('admin:system,system.pricing.manage')->name('admin-pricing.reset');

    Route::middleware('admin:content')->group(function () {
        Route::get('content', [AdminContentController::class, 'index'])->name('admin-content.index');
        Route::get('content/faqs', [AdminContentController::class, 'faqs'])->name('admin-content.faqs.index');
        Route::get('content/featured-boosters', [AdminContentController::class, 'featuredBoosters'])->name('admin-content.featured-boosters.index');
        Route::get('content/addon-tooltips', [AdminContentController::class, 'addonTooltips'])->name('admin-content.addon-tooltips.index');
        Route::get('pages', [AdminPageController::class, 'index'])->name('admin-pages.index');
        Route::get('pages/{pageKey}/edit', [AdminPageController::class, 'edit'])->name('admin-pages.edit');
        Route::patch('pages/{pageKey}', [AdminPageController::class, 'update'])->name('admin-pages.update');
        Route::post('faqs', [AdminContentController::class, 'storeFaq'])->name('admin-faqs.store');
        Route::patch('faqs/{faq}', [AdminContentController::class, 'updateFaq'])->name('admin-faqs.update');
        Route::delete('faqs/{faq}', [AdminContentController::class, 'destroyFaq'])->name('admin-faqs.destroy');
        Route::post('featured-boosters', [AdminContentController::class, 'storeFeaturedBooster'])->name('admin-featured-boosters.store');
        Route::patch('featured-boosters/{featuredBooster}', [AdminContentController::class, 'updateFeaturedBooster'])->name('admin-featured-boosters.update');
        Route::delete('featured-boosters/{featuredBooster}', [AdminContentController::class, 'destroyFeaturedBooster'])->name('admin-featured-boosters.destroy');
        Route::patch('addon-tooltips/{addonSlug}', [AdminContentController::class, 'updateAddonTooltip'])->name('admin-addon-tooltips.update');
    });

    Route::middleware('admin:marketplace,marketplace.catalog.view')->group(function () {
        Route::get('marketplace/games', [AdminMarketplaceGameController::class, 'index'])->name('admin-marketplace.games.index');
        Route::get('marketplace/games/create', [AdminMarketplaceGameController::class, 'create'])->name('admin-marketplace.games.create');
        Route::get('marketplace/games/{game}/edit', [AdminMarketplaceGameController::class, 'edit'])->name('admin-marketplace.games.edit');
        Route::get('marketplace/services', [AdminMarketplaceServiceController::class, 'index'])->name('admin-marketplace.services.index');
        Route::get('marketplace/services/create', [AdminMarketplaceServiceController::class, 'create'])->name('admin-marketplace.services.create');
        Route::get('marketplace/services/{service}/edit', [AdminMarketplaceServiceController::class, 'edit'])->name('admin-marketplace.services.edit');
        Route::get('marketplace/addons', [AdminMarketplaceAddonController::class, 'index'])->name('admin-marketplace.addons.index');
        Route::get('marketplace/addons/create', [AdminMarketplaceAddonController::class, 'create'])->name('admin-marketplace.addons.create');
        Route::get('marketplace/addons/{addon}/edit', [AdminMarketplaceAddonController::class, 'edit'])->name('admin-marketplace.addons.edit');
    });

    Route::middleware('admin:marketplace,marketplace.catalog.manage')->group(function () {
        Route::post('marketplace/games', [AdminMarketplaceGameController::class, 'store'])->name('admin-marketplace.games.store');
        Route::patch('marketplace/games/{game}', [AdminMarketplaceGameController::class, 'update'])->name('admin-marketplace.games.update');
        Route::patch('marketplace/games/{game}/archive', [AdminMarketplaceGameController::class, 'archive'])->name('admin-marketplace.games.archive');
        Route::patch('marketplace/games/{game}/publish', [AdminMarketplaceGameController::class, 'publish'])->name('admin-marketplace.games.publish');
        Route::post('marketplace/services', [AdminMarketplaceServiceController::class, 'store'])->name('admin-marketplace.services.store');
        Route::patch('marketplace/services/{service}', [AdminMarketplaceServiceController::class, 'update'])->name('admin-marketplace.services.update');
        Route::patch('marketplace/services/{service}/archive', [AdminMarketplaceServiceController::class, 'archive'])->name('admin-marketplace.services.archive');
        Route::patch('marketplace/services/{service}/publish', [AdminMarketplaceServiceController::class, 'publish'])->name('admin-marketplace.services.publish');
        Route::post('marketplace/addons', [AdminMarketplaceAddonController::class, 'store'])->name('admin-marketplace.addons.store');
        Route::patch('marketplace/addons/{addon}', [AdminMarketplaceAddonController::class, 'update'])->name('admin-marketplace.addons.update');
        Route::patch('marketplace/addons/{addon}/archive', [AdminMarketplaceAddonController::class, 'archive'])->name('admin-marketplace.addons.archive');
        Route::patch('marketplace/addons/{addon}/publish', [AdminMarketplaceAddonController::class, 'publish'])->name('admin-marketplace.addons.publish');
    });

    Route::middleware('admin:marketing')->group(function () {
        Route::get('reviews', [AdminReviewController::class, 'index'])->name('admin-reviews.index');
        Route::get('reviews/create', [AdminReviewController::class, 'create'])->name('admin-reviews.create');
        Route::post('reviews', [AdminReviewController::class, 'store'])->name('admin-reviews.store');
        Route::get('reviews/{review}/edit', [AdminReviewController::class, 'edit'])->name('admin-reviews.edit');
        Route::patch('reviews/{review}', [AdminReviewController::class, 'update'])->name('admin-reviews.update');
        Route::delete('reviews/{review}', [AdminReviewController::class, 'destroy'])->name('admin-reviews.destroy');
        Route::get('blog-articles', [AdminBlogArticleController::class, 'index'])->name('admin-blog-articles.index');
        Route::get('blog-articles/create', [AdminBlogArticleController::class, 'create'])->name('admin-blog-articles.create');
        Route::post('blog-articles', [AdminBlogArticleController::class, 'store'])->name('admin-blog-articles.store');
        Route::get('blog-articles/{blogArticle}/edit', [AdminBlogArticleController::class, 'edit'])->name('admin-blog-articles.edit');
        Route::patch('blog-articles/{blogArticle}', [AdminBlogArticleController::class, 'update'])->name('admin-blog-articles.update');
        Route::patch('blog-articles/{blogArticle}/publish', [AdminBlogArticleController::class, 'publish'])->name('admin-blog-articles.publish');
        Route::patch('blog-articles/{blogArticle}/unpublish', [AdminBlogArticleController::class, 'unpublish'])->name('admin-blog-articles.unpublish');
        Route::get('promotions', [AdminPromotionController::class, 'index'])->name('admin-promotions.index');
        Route::post('promotions', [AdminPromotionController::class, 'store'])->name('admin-promotions.store');
        Route::get('promotions/{promotion}/edit', [AdminPromotionController::class, 'edit'])->name('admin-promotions.edit');
        Route::patch('promotions/{promotion}', [AdminPromotionController::class, 'update'])->name('admin-promotions.update');
        Route::patch('promotions/{promotion}/toggle-active', [AdminPromotionController::class, 'toggleActive'])->name('admin-promotions.toggle-active');
        Route::patch('promotions/{promotion}/toggle-homepage', [AdminPromotionController::class, 'toggleHomepage'])->name('admin-promotions.toggle-homepage');
        Route::delete('promotions/{promotion}', [AdminPromotionController::class, 'destroy'])->name('admin-promotions.destroy');
        Route::get('promo-codes', [AdminPromoCodeController::class, 'index'])->name('admin-promo-codes.index');
        Route::post('promo-codes', [AdminPromoCodeController::class, 'store'])->name('admin-promo-codes.store');
        Route::get('promo-codes/{promoCode}/details', [AdminPromoCodeController::class, 'details'])->name('admin-promo-codes.details');
        Route::get('promo-codes/{promoCode}/edit', [AdminPromoCodeController::class, 'edit'])->name('admin-promo-codes.edit');
        Route::patch('promo-codes/{promoCode}/deactivate', [AdminPromoCodeController::class, 'deactivate'])->name('admin-promo-codes.deactivate');
        Route::patch('promo-codes/{promoCode}', [AdminPromoCodeController::class, 'update'])->name('admin-promo-codes.update');
        Route::delete('promo-codes/{promoCode}', [AdminPromoCodeController::class, 'destroy'])->name('admin-promo-codes.destroy');
    });

    Route::middleware('admin:people')->group(function () {
        Route::get('booster-applications', [AdminBoosterController::class, 'applications'])->name('admin-booster-applications');
        Route::get('booster-applications/{boosterApplication}', [AdminBoosterController::class, 'editApplication'])->name('admin-booster-applications.edit');
        Route::patch('booster-applications/{boosterApplication}', [AdminBoosterController::class, 'updateApplication'])->name('admin-booster-applications.update');
        Route::get('booster-applications/{boosterApplication}/convert', [AdminBoosterController::class, 'convertApplication'])->name('admin-booster-applications.convert');
        Route::get('boosters', [AdminBoosterController::class, 'index'])->name('admin-boosters.index');
        Route::get('boosters/create', [AdminBoosterController::class, 'create'])->name('admin-boosters.create');
        Route::get('boosters/{booster}', [AdminBoosterController::class, 'show'])->name('admin-boosters.show');
        Route::get('boosters/{booster}/edit', [AdminBoosterController::class, 'edit'])->name('admin-boosters.edit');
        Route::post('boosters', [AdminBoosterController::class, 'store'])->name('admin-boosters.store');
        Route::patch('boosters/{user}/status', [AdminBoosterController::class, 'toggleStatus'])->name('admin-boosters.status');
        Route::patch('boosters/{booster}', [AdminBoosterController::class, 'update'])->name('admin-boosters.update');
        Route::get('customers', [AdminCustomerController::class, 'index'])->name('admin-customers.index');
        Route::get('customers/create', [AdminCustomerController::class, 'create'])->name('admin-customers.create');
        Route::get('customers/{user}', [AdminCustomerController::class, 'show'])->name('admin-customers.show');
        Route::get('customers/{user}/edit', [AdminCustomerController::class, 'edit'])->name('admin-customers.edit');
        Route::post('customers', [AdminCustomerController::class, 'store'])->name('admin-customers.store');
        Route::patch('customers/{user}/status', [AdminCustomerController::class, 'toggleStatus'])->name('admin-customers.status');
        Route::patch('customers/{user}', [AdminCustomerController::class, 'update'])->name('admin-customers.update');
        Route::get('contact-messages', [AdminContactMessageController::class, 'index'])->name('admin-contact-messages.index');
        Route::get('contact-messages/{contactMessage}', [AdminContactMessageController::class, 'edit'])->name('admin-contact-messages.edit');
        Route::patch('contact-messages/{contactMessage}', [AdminContactMessageController::class, 'update'])->name('admin-contact-messages.update');
    });

    Route::middleware('admin:finance')->group(function () {
        Route::get('withdrawal-requests', [AdminFinanceController::class, 'withdrawalRequests'])->name('admin-withdrawal-requests.index');
        Route::patch('withdrawal-requests/{withdrawalRequest}', [AdminFinanceController::class, 'updateWithdrawalRequestStatus'])->name('admin-withdrawal-requests.update');
        Route::get('wallet-adjustments', [AdminFinanceController::class, 'walletAdjustments'])->name('admin-wallet-adjustments.index');
        Route::post('wallet-adjustments', [AdminFinanceController::class, 'storeWalletAdjustment'])->name('admin-wallet-adjustments.store');
        Route::get('income-statement', [AdminFinanceController::class, 'incomeStatement'])->name('admin-income-statement');
        Route::get('income-statement/export/excel', [AdminFinanceController::class, 'exportIncomeStatementExcel'])->name('admin-income-statement.export.excel');
        Route::get('income-statement/export/pdf', [AdminFinanceController::class, 'exportIncomeStatementPdf'])->name('admin-income-statement.export.pdf');
    });

    Route::middleware('admin:operations')->group(function () {
        Route::get('chats', [AdminDashboardController::class, 'chats'])->name('admin-chats');
        Route::redirect('chat', '/admin/chats', 301);
        Route::get('chat/{order:order_number}', [ChatRouteRedirectController::class, 'admin']);
        Route::get('chats/{order:order_number}', [AdminDashboardController::class, 'showChat'])->name('admin-chats.show');
        Route::get('custom-order', [AdminOrderController::class, 'customOrder'])->name('admin-custom-order');
        Route::post('custom-order', [AdminOrderController::class, 'storeManual'])->name('admin-orders.store-manual');
        Route::get('total-order', [AdminOrderController::class, 'index'])->name('admin-total-order');
        Route::get('total-order/export', [AdminOrderController::class, 'export'])->name('admin-total-order.export');
        Route::get('orders/{order}/edit', [AdminOrderController::class, 'edit'])->name('admin-orders.edit');
        Route::get('orders/{order}/completion-proof', [AdminOrderController::class, 'completionProof'])->name('admin-orders.completion-proof');
        Route::patch('orders/{order}', [AdminOrderController::class, 'update'])->name('admin-orders.update');
        Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('admin-orders.status');
        Route::patch('orders/{order}/booster', [AdminOrderController::class, 'assignBooster'])
            ->name('admin-orders.assign-booster');
    });

    Route::post('maintenance-mode/challenge', [AdminMaintenanceModeController::class, 'challenge'])
        ->middleware('throttle:maintenance-mode-challenge')
        ->middleware('admin:system')
        ->name('admin-maintenance-mode.challenge');
    Route::post('maintenance-mode/challenge/confirm', [AdminMaintenanceModeController::class, 'confirmPhrase'])
        ->middleware('throttle:maintenance-mode-challenge')
        ->middleware('admin:system')
        ->name('admin-maintenance-mode.confirm');
    Route::post('maintenance-mode/challenge/captcha', [AdminMaintenanceModeController::class, 'verifyCaptcha'])
        ->middleware('throttle:maintenance-mode-update')
        ->middleware('admin:system')
        ->name('admin-maintenance-mode.captcha');
    Route::post('maintenance-mode/challenge/password', [AdminMaintenanceModeController::class, 'verifyPassword'])
        ->middleware('throttle:maintenance-mode-update')
        ->middleware('admin:system')
        ->name('admin-maintenance-mode.password');
    Route::patch('maintenance-mode', [AdminMaintenanceModeController::class, 'update'])
        ->middleware('throttle:maintenance-mode-update')
        ->middleware('admin:system')
        ->name('admin-maintenance-mode.update');
    Route::post('profile-photo', [AuthWebController::class, 'updateProfilePhoto'])->name('admin.profile-photo.update');
});
