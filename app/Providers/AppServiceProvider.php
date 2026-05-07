<?php

namespace App\Providers;

use App\Console\Commands\PrunePendingCheckoutsCommand;
use App\Console\Commands\ReconcileWithdrawalHistoryCommand;
use App\Console\Commands\RedactUserHistoryCommand;
use App\Console\Commands\RepairOrderPaymentIdentifiersCommand;
use App\Console\Commands\RetryDiscordNotificationDispatchesCommand;
use App\Contracts\Security\FileUploadScanner;
use App\Models\Order;
use App\Models\User;
use App\Observers\OrderObserver;
use App\Queries\PublicSocialProofQuery;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Auth\Socialite\DiscordProvider;
use App\Services\Discord\DiscordNotifier;
use App\Services\Discord\DiscordWebhookClient;
use App\Services\Mail\AccountLifecycleEmailNotifier;
use App\Services\Mail\BoosterEmailNotifier;
use App\Services\Mail\CustomerOrderEmailNotifier;
use App\Services\Mail\TransactionalMailDispatcher;
use App\Services\MaintenanceModeChallengeService;
use App\Services\Payments\CryptomusClient;
use App\Services\Payments\PaymentInitializationPipeline;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PaymentWebhookEventService;
use App\Services\Payments\PendingCheckoutStore;
use App\Services\Payments\Providers\CryptomusPaymentProvider;
use App\Services\Payments\Providers\StripePaymentProvider;
use App\Services\Security\BasicImageUploadScanner;
use App\Services\Security\ProfilePhotoStorageService;
use App\Services\Security\PromotionImageStorageService;
use App\Services\SystemSettingService;
use App\Support\Api\ApiErrorResponder;
use App\Support\BoostingCatalog;
use App\Support\Cms\PageRegistry;
use App\Support\GameCatalog;
use App\Support\Logging\AppEventLogger;
use App\Support\MarketplaceNavigation;
use App\Support\Nickname;
use App\Support\Pricing\PricingEngineManager;
use App\Support\Pricing\ValorantPricingConfigRepository;
use App\Support\Pricing\ValorantPricingConfigValidator;
use App\Support\Runtime\StartupConfigurationValidator;
use App\Support\ValorantAgentCatalog;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\View\View as ViewInstance;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FileUploadScanner::class, BasicImageUploadScanner::class);
        $this->app->singleton(DiscordWebhookClient::class);
        $this->app->singleton(DiscordNotifier::class);
        $this->app->singleton(CustomerOrderEmailNotifier::class);
        $this->app->singleton(TransactionalMailDispatcher::class);
        $this->app->singleton(AccountLifecycleEmailNotifier::class);
        $this->app->singleton(BoosterEmailNotifier::class);
        $this->app->singleton(ProfilePhotoStorageService::class);
        $this->app->singleton(PromotionImageStorageService::class);
        $this->app->singleton(SystemSettingService::class);
        $this->app->singleton(AdminAuditLogger::class);
        $this->app->singleton(MaintenanceModeChallengeService::class);
        $this->app->singleton(AppEventLogger::class);
        $this->app->singleton(ApiErrorResponder::class);
        $this->app->singleton(StartupConfigurationValidator::class);
        $this->app->singleton(GameCatalog::class);
        $this->app->singleton(MarketplaceNavigation::class);
        $this->app->singleton(ValorantPricingConfigValidator::class);
        $this->app->singleton(ValorantPricingConfigRepository::class);
        $this->app->singleton(PricingEngineManager::class);
        $this->app->singleton(PendingCheckoutStore::class);
        $this->app->singleton(PaymentWebhookEventService::class);
        $this->app->singleton(CryptomusClient::class);
        $this->app->singleton(StripePaymentProvider::class);
        $this->app->singleton(CryptomusPaymentProvider::class);
        $this->app->tag([StripePaymentProvider::class, CryptomusPaymentProvider::class], 'payment.providers');

        $this->app->singleton(PaymentManager::class, function ($app) {
            return new PaymentManager($app->tagged('payment.providers'));
        });
        $this->app->singleton(PaymentInitializationPipeline::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureUrlScheme();
        $this->app->make(StartupConfigurationValidator::class)->validate();
        $this->configureSocialiteProviders();
        Order::observe(OrderObserver::class);
        $this->configureRateLimiting();
        $this->configureRouteBindings();
        Gate::define('viewWebSocketsDashboard', fn ($user) => User::normalizeRole($user?->role) === User::ROLE_SUPER_ADMIN);

        if ($this->app->runningInConsole()) {
            $this->commands([
                PrunePendingCheckoutsCommand::class,
                ReconcileWithdrawalHistoryCommand::class,
                RedactUserHistoryCommand::class,
                RepairOrderPaymentIdentifiersCommand::class,
                RetryDiscordNotificationDispatchesCommand::class,
            ]);

            Event::listen(CommandFinished::class, function (CommandFinished $event): void {
                if (! in_array($event->command, ['migrate', 'migrate:fresh', 'migrate:refresh'], true)) {
                    return;
                }

                foreach ([
                    'Database\\Seeders\\GameCatalogSeeder',
                    'Database\\Seeders\\PlatformContentSeeder',
                ] as $seeder) {
                    Artisan::call('db:seed', [
                        '--class' => $seeder,
                        '--force' => true,
                    ]);
                }
            });
        }

        View::composer(['home', 'marketplace.service', 'checkout', 'welcome', 'admin.custom-order', 'user.upgrade-order'], function (ViewInstance $view): void {
            $this->shareBoostingCatalog($view, includeFrontendPricing: true, includeAgents: true);
        });

        View::composer(['admin.orders.edit'], function (ViewInstance $view): void {
            $this->shareBoostingCatalog($view, includeFrontendPricing: false, includeAgents: false);
        });

        View::composer(['admin.chats.show', 'booster.chats.show', 'user.chats.show'], function (ViewInstance $view): void {
            $this->shareBoostingAgents($view);
        });

        View::composer('layouts.layout', function (ViewInstance $view): void {
            $view->with('marketplaceNavigation', $this->app->make(MarketplaceNavigation::class)->forRequest(request()));
        });

        View::composer(['layouts.layout', 'welcome'], function (ViewInstance $view): void {
            $publicRoutes = [
                'home',
                'games.*',
                'blog.*',
                'terms-and-conditions',
                'signup',
                'login',
                'oauth.*',
                'refund-policy',
                'faq',
                'contact',
                'code-of-ethics',
                'checkout',
                'reviews',
                'become-booster',
            ];

            $shouldShow = $view->getName() === 'welcome' || request()->routeIs($publicRoutes);

            if (! $shouldShow) {
                $view->with('showPublicSocialProof', false);
                $view->with('publicSocialProofItems', []);

                return;
            }

            $items = $this->app->make(PublicSocialProofQuery::class)->execute();

            $view->with('showPublicSocialProof', count($items) > 0);
            $view->with('publicSocialProofItems', $items);
        });
    }

    protected function shareBoostingCatalog(ViewInstance $view, bool $includeFrontendPricing = false, bool $includeAgents = false): void
    {
        /** @var GameCatalog $gameCatalog */
        $gameCatalog = $this->app->make(GameCatalog::class);
        $routeGame = request()->route('game');
        $gameSlug = $gameCatalog->normalizeSlug(is_scalar($routeGame) ? $routeGame : request()->query('game', GameCatalog::DEFAULT_GAME_SLUG));
        $game = $gameCatalog->exists($gameSlug)
            ? $gameCatalog->game($gameSlug)
            : $gameCatalog->game(GameCatalog::DEFAULT_GAME_SLUG);
        $pricingPayload = $this->app->make(ValorantPricingConfigRepository::class)->publicPayload($game['slug'] ?? GameCatalog::DEFAULT_GAME_SLUG);
        $productConfig = $gameCatalog->publicPayload(
            $game['slug'] ?? GameCatalog::DEFAULT_GAME_SLUG,
            $pricingPayload['pricingPreview'] ?? []
        );

        $view->with('ggwpGame', $game);
        $view->with('ggwpGames', $gameCatalog->all(includeDrafts: true));
        $view->with('ggwpGameSlug', $game['slug'] ?? GameCatalog::DEFAULT_GAME_SLUG);
        $view->with('ggwpServiceOptions', $productConfig['services'] ?? BoostingCatalog::serviceOptions());
        $view->with('ggwpRankOptions', $productConfig['ranks'] ?? BoostingCatalog::rankOptions());
        $view->with('ggwpRankOptionsWithRadiant', $productConfig['ranksWithRadiant'] ?? BoostingCatalog::rankOptionsWithRadiant());
        $view->with('ggwpDefaultCurrentRank', data_get($productConfig, 'defaults.currentRank', BoostingCatalog::defaultCurrentRank()));
        $view->with('ggwpDefaultDesiredRank', data_get($productConfig, 'defaults.desiredRank', BoostingCatalog::defaultDesiredRank()));
        $view->with('ggwpRegions', BoostingCatalog::regions());
        $view->with('ggwpPlatforms', BoostingCatalog::platforms());
        $view->with('ggwpBoostModes', BoostingCatalog::boostModes());
        $view->with('ggwpBoostModeOptions', BoostingCatalog::boostModeOptions());
        $view->with('ggwpAverageRrOptions', BoostingCatalog::averageRrOptions());
        $view->with('ggwpAverageRrOptionChoices', BoostingCatalog::averageRrOptionChoices());
        $view->with('ggwpAddons', $productConfig['addons'] ?? BoostingCatalog::addons());

        if ($includeFrontendPricing) {
            $view->with('ggwpProductConfig', $productConfig);
        }

        if ($includeAgents) {
            $view->with('ggwpValorantAgents', ValorantAgentCatalog::all());
        }
    }

    protected function shareBoostingAgents(ViewInstance $view): void
    {
        $view->with('ggwpValorantAgents', ValorantAgentCatalog::all());
    }

    protected function configureSocialiteProviders(): void
    {
        $socialite = $this->app->make(SocialiteFactory::class);

        if (! method_exists($socialite, 'extend')) {
            return;
        }

        $socialite->extend('discord', function ($app) {
            $config = (array) $app['config']->get('services.discord', []);
            $redirect = $config['redirect'] ?? null;

            if (Str::startsWith($redirect ?? '', '/')) {
                $redirect = $app['url']->to($redirect);
            }

            return (new DiscordProvider(
                $app['request'],
                $config['client_id'] ?? '',
                $config['client_secret'] ?? '',
                $redirect,
                Arr::get($config, 'guzzle', []),
            ))->scopes($config['scopes'] ?? []);
        });
    }

    protected function configureUrlScheme(): void
    {
        $appUrlScheme = parse_url((string) config('app.url'), PHP_URL_SCHEME);

        if ($appUrlScheme === 'https' || (bool) config('session.secure')) {
            URL::forceScheme('https');
        }
    }

    protected function configureRouteBindings(): void
    {
        Route::bind('pageKey', function (mixed $value): string {
            $key = trim((string) $value);

            abort_unless($this->app->make(PageRegistry::class)->has($key), 404);

            return $key;
        });

        Route::bind('booster', function (mixed $value): User {
            if (! Nickname::isValid($value)) {
                throw new NotFoundHttpException;
            }

            $booster = User::query()
                ->where('role', 'booster')
                ->where('nickname_normalized', Nickname::normalized($value))
                ->first();

            if (! $booster instanceof User) {
                throw new NotFoundHttpException;
            }

            return $booster;
        });
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login-route', function (Request $request) {
            $email = $this->normalizedLimiterValue($request->input('email'));

            return [
                Limit::perMinute(20)->by('login-ip:'.$request->ip()),
                Limit::perMinute(10)->by('login-email-ip:'.$email.'|'.$request->ip()),
                Limit::perHour(50)->by('login-email:'.$email),
            ];
        });

        RateLimiter::for('register-route', function (Request $request) {
            $email = $this->normalizedLimiterValue($request->input('email'));

            return [
                Limit::perMinute(6)->by('register-ip:'.$request->ip()),
                Limit::perHour(12)->by('register-email:'.$email),
            ];
        });

        RateLimiter::for('password-reset-link', function (Request $request) {
            $email = $this->normalizedLimiterValue($request->input('email'));

            return [
                Limit::perHour(3)->by('password-reset-link-ip:'.$request->ip()),
                Limit::perHour(3)->by('password-reset-link-email:'.$email),
            ];
        });

        RateLimiter::for('password-reset-submit', function (Request $request) {
            $email = $this->normalizedLimiterValue($request->input('email'));

            return [
                Limit::perHour(3)->by('password-reset-submit-ip:'.$request->ip()),
                Limit::perHour(3)->by('password-reset-submit-email:'.$email),
            ];
        });

        RateLimiter::for('oauth-route', fn (Request $request) => [
            Limit::perMinute(20)->by('oauth-route-ip:'.$request->ip()),
            Limit::perHour(80)->by('oauth-route-session:'.$this->sessionOrIpKey($request)),
        ]);

        RateLimiter::for('oauth-complete-profile', fn (Request $request) => [
            Limit::perMinute(6)->by('oauth-profile-ip:'.$request->ip()),
            Limit::perHour(12)->by('oauth-profile-session:'.$this->sessionOrIpKey($request)),
        ]);

        RateLimiter::for('contact-form', function (Request $request) {
            $actor = $this->sessionOrIpKey($request);
            $email = $this->normalizedLimiterValue($request->input('email'));
            $orderReference = $this->normalizedLimiterValue($request->input('order_reference'));

            return [
                Limit::perMinute(5)->by('contact-ip:'.$request->ip()),
                Limit::perHour(4)->by('contact-actor:'.$actor),
                Limit::perHour(3)->by('contact-message:'.$email.'|'.$orderReference),
            ];
        });

        RateLimiter::for('booster-application', function (Request $request) {
            $email = $this->normalizedLimiterValue($request->input('email'));
            $discord = $this->normalizedLimiterValue($request->input('discord'));

            return [
                Limit::perMinute(5)->by('booster-app-ip:'.$request->ip()),
                Limit::perHour(3)->by('booster-app-email:'.$email),
                Limit::perHour(3)->by('booster-app-discord:'.$discord),
            ];
        });

        RateLimiter::for('promo-preview', function (Request $request) {
            $actor = $this->actorKey($request);
            $promoCode = $this->normalizedLimiterValue($request->input('promoCode'));

            return [
                Limit::perMinute(20)->by('promo-ip:'.$request->ip()),
                Limit::perMinute(10)->by('promo-actor:'.$actor),
                Limit::perMinute(5)->by('promo-code:'.$actor.'|'.$promoCode),
            ];
        });

        RateLimiter::for('pricing-calculate', fn (Request $request) => [
            Limit::perMinute(300)->by('pricing-ip:'.$request->ip()),
            Limit::perMinute(240)->by('pricing-actor:'.$this->actorKey($request)),
        ]);

        RateLimiter::for('public-api-read', fn (Request $request) => [
            Limit::perMinute(120)->by('public-api-read-ip:'.$request->ip()),
            Limit::perMinute(90)->by('public-api-read-actor:'.$this->actorKey($request)),
        ]);

        RateLimiter::for('health-readiness', fn (Request $request) => [
            Limit::perMinute(30)->by('health-readiness-ip:'.$request->ip()),
        ]);

        RateLimiter::for('checkout-submit', function (Request $request) {
            $email = $this->normalizedLimiterValue($request->input('email'));

            return [
                Limit::perMinute(6)->by('checkout-actor:'.$this->actorKey($request)),
                Limit::perHour(10)->by('checkout-email:'.$email),
            ];
        });

        RateLimiter::for('booster-withdrawal', fn (Request $request) => [
            Limit::perHour(3)->by('withdraw-user:'.$this->actorKey($request)),
            Limit::perDay(10)->by('withdraw-ip:'.$request->ip()),
        ]);

        RateLimiter::for('customer-order-actions', fn (Request $request) => [
            Limit::perMinute(12)->by('customer-order-action:'.$this->actorKey($request)),
            Limit::perMinute(6)->by('customer-order-action-route:'.$this->actorKey($request).'|'.$this->normalizedLimiterValue($request->route()?->getName())),
        ]);

        RateLimiter::for('order-progress-update', function (Request $request) {
            $orderRouteValue = $request->route('order');
            $order = is_object($orderRouteValue) && method_exists($orderRouteValue, 'getKey')
                ? (string) $orderRouteValue->getKey()
                : (string) ($orderRouteValue ?? 'unknown');

            return [
                Limit::perMinute(30)->by('order-progress-actor:'.$this->actorKey($request)),
                Limit::perMinute(12)->by('order-progress-order:'.$this->actorKey($request).'|'.$order),
            ];
        });

        RateLimiter::for('maintenance-mode-challenge', fn (Request $request) => [
            Limit::perMinute(6)->by('maintenance-mode-challenge-user:'.$this->actorKey($request)),
            Limit::perMinute(10)->by('maintenance-mode-challenge-ip:'.$request->ip()),
        ]);

        RateLimiter::for('maintenance-mode-update', fn (Request $request) => [
            Limit::perMinute(4)->by('maintenance-mode-update-user:'.$this->actorKey($request)),
            Limit::perMinute(8)->by('maintenance-mode-update-ip:'.$request->ip()),
        ]);

        RateLimiter::for('stripe-webhook', function (Request $request) {
            $signatureKey = substr(hash('sha256', (string) $request->header('Stripe-Signature', 'missing')), 0, 24);

            return [
                Limit::perMinute(240)->by('stripe-webhook-ip:'.$request->ip()),
                Limit::perMinute(60)->by('stripe-webhook-signature:'.$signatureKey),
            ];
        });

        RateLimiter::for('cryptomus-webhook', function (Request $request) {
            $payload = $request->json()->all();
            $payload = is_array($payload) ? $payload : [];
            $signature = (string) ($payload['sign'] ?? 'missing');
            $orderId = $this->normalizedLimiterValue($payload['order_id'] ?? 'unknown');

            return [
                Limit::perMinute(240)->by('cryptomus-webhook-ip:'.$request->ip()),
                Limit::perMinute(60)->by('cryptomus-webhook-order:'.$orderId),
                Limit::perMinute(60)->by('cryptomus-webhook-signature:'.substr(hash('sha256', $signature), 0, 24)),
            ];
        });

        RateLimiter::for('chat-history', function (Request $request) {
            $actor = $this->actorKey($request);
            $thread = $this->normalizedLimiterValue($request->route('threadType'));
            $orderRouteValue = $request->route('order');
            $order = is_object($orderRouteValue) && method_exists($orderRouteValue, 'getKey')
                ? (string) $orderRouteValue->getKey()
                : (string) ($orderRouteValue ?? 'unknown');

            return [
                Limit::perMinute(120)->by('chat-history-actor:'.$actor),
                Limit::perMinute(60)->by('chat-history-thread:'.$actor.'|'.$order.'|'.$thread),
            ];
        });

        RateLimiter::for('chat-send', function (Request $request) {
            $actor = $this->actorKey($request);
            $thread = $this->normalizedLimiterValue($request->route('threadType'));
            $orderRouteValue = $request->route('order');
            $order = is_object($orderRouteValue) && method_exists($orderRouteValue, 'getKey')
                ? (string) $orderRouteValue->getKey()
                : (string) ($orderRouteValue ?? 'unknown');

            return [
                Limit::perMinute(20)->by('chat-send-actor:'.$actor),
                Limit::perMinute(8)->by('chat-send-thread:'.$actor.'|'.$order.'|'.$thread),
            ];
        });
    }

    protected function actorKey(Request $request): string
    {
        return (string) ($request->user()?->id ?? $this->sessionOrIpKey($request));
    }

    protected function sessionOrIpKey(Request $request): string
    {
        $sessionId = $request->session()?->getId();

        return $sessionId ? 'session:'.$sessionId : 'ip:'.$request->ip();
    }

    protected function normalizedLimiterValue(mixed $value): string
    {
        $string = Str::lower(trim((string) $value));

        return $string !== '' ? Str::transliterate($string) : 'anonymous';
    }
}
