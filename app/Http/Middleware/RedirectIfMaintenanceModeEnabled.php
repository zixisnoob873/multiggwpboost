<?php

namespace App\Http\Middleware;

use App\Services\SystemSettingService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfMaintenanceModeEnabled
{
    public function __construct(protected SystemSettingService $systemSettingService) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->systemSettingService->isMaintenanceModeEnabled()) {
            return $next($request);
        }

        if ($this->shouldBypass($request)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return new JsonResponse([
                'message' => 'The website is temporarily under maintenance.',
                'redirect_to' => route('under-maintenance'),
            ], 503);
        }

        return redirect()->route('under-maintenance');
    }

    protected function shouldBypass(Request $request): bool
    {
        return $request->routeIs('under-maintenance')
            || $request->is('under_maintenance')
            || $request->routeIs('admin-*')
            || $request->is('admin', 'admin/*')
            || $request->routeIs('blog.*')
            || $request->is('blog', 'blog/*')
            || $request->routeIs('login', 'login.*', 'signup', 'signup.*', 'logout', 'password.*', 'passwords.*', 'oauth.*')
            || $request->is('login', 'signup', 'register', 'register/*', 'auth/*', 'forgot-password', 'forgot-password/*', 'reset-password', 'reset-password/*')
            || $request->routeIs('stripe.webhook', 'cryptomus.webhook', 'orders.success', 'pricing.calculate', 'pricing.config', 'checkout.promo.preview', 'api.*');
    }
}
