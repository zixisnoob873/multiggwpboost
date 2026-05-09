<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = bin2hex(random_bytes(16));
        View::share('cspNonce', $nonce);
        $viteOrigin = $this->viteDevServerOrigin();
        $viteWebsocketOrigin = $this->viteWebsocketOrigin($viteOrigin);
        $broadcastOrigins = $this->broadcastOrigins();

        /** @var Response $response */
        $response = $next($request);

        $tawkWidgetAllowed = $this->tawkWidgetAllowed($request);

        $scriptSources = array_filter([
            "'self'",
            "'nonce-{$nonce}'",
            'https://cdn.jsdelivr.net',
            ...$this->googleTagScriptSources(),
            ...$this->postHogSources(),
            ...$this->tawkSources($tawkWidgetAllowed),
            $viteOrigin,
        ]);

        // Keep 'unsafe-inline' in style-src for now: public fallback/maintenance templates still
        // contain inline <style> blocks, several Blade views use style attributes for progress and
        // image previews, and JS writes dynamic dimensions/CSS variables for chat, carousel, and
        // rank-picker UI. Remove it only after those styles move to classes, nonce-backed <style>
        // blocks, or another CSP-safe pattern.
        $styleSources = array_filter([
            "'self'",
            "'unsafe-inline'",
            'https://cdn.jsdelivr.net',
            'https://fonts.googleapis.com',
            'https://fonts.bunny.net',
            ...$this->tawkSources($tawkWidgetAllowed),
            $viteOrigin,
        ]);

        $connectSources = array_filter([
            "'self'",
            $viteOrigin,
            $viteWebsocketOrigin,
            ...$broadcastOrigins,
            ...$this->googleAnalyticsConnectSources(),
            ...$this->postHogSources(),
            ...$this->tawkConnectSources($tawkWidgetAllowed),
        ]);

        $frameSources = array_filter([
            "'self'",
            ...$this->tawkSources($tawkWidgetAllowed),
        ]);

        $formActionSources = array_filter([
            "'self'",
            ...$this->hostedPaymentFormActionSources(),
            ...$this->tawkSources($tawkWidgetAllowed),
        ]);

        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            'form-action '.implode(' ', $formActionSources),
            "frame-ancestors 'none'",
            "object-src 'none'",
            'script-src '.implode(' ', $scriptSources),
            "script-src-attr 'none'",
            'style-src '.implode(' ', $styleSources),
            "font-src 'self' data: https:",
            "img-src 'self' data: https:",
            'connect-src '.implode(' ', $connectSources),
            'frame-src '.implode(' ', $frameSources),
        ];

        if ($this->strictBrowserHardeningEnabled($request)) {
            $directives[] = 'upgrade-insecure-requests';
        }

        $csp = implode('; ', $directives);

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // The site has no same-origin iframe requirement; block all framing for modern and legacy
        // browsers with matching CSP frame-ancestors and X-Frame-Options policies.
        $response->headers->set('X-Frame-Options', 'DENY');

        $response->headers->set('Permissions-Policy', 'camera=(), geolocation=(), microphone=(), payment=(), usb=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        if ($request->user()) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }

    protected function strictBrowserHardeningEnabled(Request $request): bool
    {
        return $this->productionLikeHttpsRequest($request);
    }

    protected function productionLikeHttpsRequest(Request $request): bool
    {
        return app()->environment(['production', 'staging']) && $request->isSecure();
    }

    protected function viteDevServerOrigin(): ?string
    {
        if (app()->environment(['production', 'staging'])) {
            return null;
        }

        $hotFile = public_path('hot');

        if (! is_file($hotFile)) {
            return null;
        }

        $url = trim((string) @file_get_contents($hotFile));

        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $host = (string) $parts['host'];

        if (str_contains($host, ':') && ! str_starts_with($host, '[')) {
            $host = '['.$host.']';
        }

        $origin = $parts['scheme'].'://'.$host;

        if (! empty($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }

    protected function viteWebsocketOrigin(?string $origin): ?string
    {
        if (! $origin) {
            return null;
        }

        return match (true) {
            str_starts_with($origin, 'https://') => 'wss://'.substr($origin, 8),
            str_starts_with($origin, 'http://') => 'ws://'.substr($origin, 7),
            default => null,
        };
    }

    protected function broadcastOrigins(): array
    {
        if (config('broadcasting.default') !== 'pusher' || blank(config('broadcasting.connections.pusher.key'))) {
            return [];
        }

        $host = trim((string) config('broadcasting.connections.pusher.options.host', ''));
        $port = (int) config('broadcasting.connections.pusher.options.port', 0);
        $scheme = strtolower(trim((string) config('broadcasting.connections.pusher.options.scheme', '')));

        if ($host === '' || $port < 1 || ! in_array($scheme, ['http', 'https'], true)) {
            return [];
        }

        if (app()->environment(['production', 'staging']) && ($scheme !== 'https' || $this->isLocalBrowserHost($host))) {
            return [];
        }

        $normalizedHost = Str::contains($host, ':') && ! str_starts_with($host, '[')
            ? '['.$host.']'
            : $host;

        $httpOrigin = sprintf('%s://%s:%d', $scheme, $normalizedHost, $port);
        $wsOrigin = sprintf(
            '%s://%s:%d',
            $scheme === 'https' ? 'wss' : 'ws',
            $normalizedHost,
            $port
        );

        return [$httpOrigin, $wsOrigin];
    }

    protected function isLocalBrowserHost(string $host): bool
    {
        $normalizedHost = strtolower(trim($host, "[] \t\n\r\0\x0B."));

        return in_array($normalizedHost, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true);
    }

    protected function tawkWidgetAllowed(Request $request): bool
    {
        return ! $request->routeIs('admin-*', 'admin.*', 'booster-*', 'booster.*', 'user-chats', 'user-chats.show')
            && ! $request->is('admin/*', 'booster/*', 'boost/*');
    }

    protected function tawkSources(bool $allowed): array
    {
        if (! $allowed) {
            return [];
        }

        return [
            'https://tawk.to',
            'https://*.tawk.to',
        ];
    }

    protected function hostedPaymentFormActionSources(): array
    {
        return array_filter([
            (bool) config('services.stripe.enabled', true) ? 'https://checkout.stripe.com' : null,
            (bool) config('services.cryptomus.enabled', true) ? 'https://pay.cryptomus.com' : null,
        ]);
    }

    protected function tawkConnectSources(bool $allowed): array
    {
        if (! $allowed) {
            return [];
        }

        return [
            ...$this->tawkSources($allowed),
            'wss://*.tawk.to',
        ];
    }

    protected function googleAnalyticsConnectSources(): array
    {
        if (blank(config('analytics.google.measurement_id'))) {
            return [];
        }

        return [
            'https://www.googletagmanager.com',
            'https://*.googletagmanager.com',
            'https://www.google-analytics.com',
            'https://*.google-analytics.com',
            'https://analytics.google.com',
        ];
    }

    protected function googleTagScriptSources(): array
    {
        if (blank(config('analytics.google.measurement_id'))) {
            return [];
        }

        return [
            'https://www.googletagmanager.com',
            'https://*.googletagmanager.com',
        ];
    }

    protected function postHogSources(): array
    {
        if (blank(config('analytics.posthog.key'))) {
            return [];
        }

        $origin = $this->originFromUrl((string) config('analytics.posthog.host', 'https://us.i.posthog.com'));

        return array_values(array_filter(array_unique([$origin])));
    }

    protected function originFromUrl(string $url): ?string
    {
        $parts = parse_url(trim($url));

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = (string) $parts['host'];

        if (str_contains($host, ':') && ! str_starts_with($host, '[')) {
            $host = '['.$host.']';
        }

        $origin = $scheme.'://'.$host;

        if (! empty($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }
}
