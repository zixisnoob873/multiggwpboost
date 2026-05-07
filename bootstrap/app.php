<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['web', 'auth']])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: [
            \App\Support\Privacy\CookieConsent::COOKIE_NAME,
        ]);

        $middleware->append(\App\Http\Middleware\BindRequestLoggingContext::class);

        if (filled(env('TRUSTED_PROXIES'))) {
            $middleware->trustProxies(at: env('TRUSTED_PROXIES'));
        }

        $middleware->web(append: [
            \App\Http\Middleware\RedirectIfMaintenanceModeEnabled::class,
        ]);

        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'admin' => \App\Http\Middleware\EnsureUserCanAccessAdmin::class,
            'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $exception): bool {
            return app(\App\Support\Api\ApiErrorResponder::class)->shouldRenderJson($request);
        });

        $exceptions->render(function (\Throwable $exception, Request $request) {
            if (! app(\App\Support\Api\ApiErrorResponder::class)->shouldRenderJson($request)) {
                return null;
            }

            return app(\App\Support\Api\ApiErrorResponder::class)->render($request, $exception);
        });
    })->create();
