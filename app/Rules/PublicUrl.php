<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;

class PublicUrl implements ValidationRule
{
    protected const PRIVATE_PATH_PREFIXES = [
        'admin',
        'api',
        'boost',
        'booster',
        'internal',
        'orders',
        'user',
    ];

    protected const PRIVATE_ROUTE_PREFIXES = [
        'admin',
        'api.',
        'booster',
        'customer-orders',
        'health.ready.internal',
        'orders.',
        'user.',
    ];

    protected const KNOWN_FRAGMENTS = [
        'contactForm',
        'featured-games',
        'popular-services',
        'serviceCalculator',
        'services',
        'servicesTab',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $url = trim((string) $value);

        if ($url === '') {
            return;
        }

        if (Str::startsWith($url, '#')) {
            $this->validateFragment($attribute, substr($url, 1), $fail);

            return;
        }

        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

            if (in_array($scheme, ['http', 'https'], true)) {
                return;
            }

            $fail('The :attribute field must be an HTTP(S) URL, a public site-relative URL, or a known page fragment.');

            return;
        }

        if (! Str::startsWith($url, '/') || Str::startsWith($url, '//') || str_contains($url, '\\')) {
            $fail('The :attribute field must be an HTTP(S) URL, a public site-relative URL, or a known page fragment.');

            return;
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            $fail('The :attribute field must be an HTTP(S) URL, a public site-relative URL, or a known page fragment.');

            return;
        }

        $path = (string) ($parts['path'] ?? '/');
        $fragment = (string) ($parts['fragment'] ?? '');

        if ($path === '') {
            $path = '/';
        }

        if ($this->isPrivatePath($path) || ! $this->matchesPublicGetRoute($path, (string) ($parts['query'] ?? ''))) {
            $fail('The :attribute field must point to an existing public page.');

            return;
        }

        if ($fragment !== '') {
            $this->validateFragment($attribute, $fragment, $fail);
        }
    }

    protected function matchesPublicGetRoute(string $path, string $query): bool
    {
        try {
            $requestUri = $path.($query !== '' ? '?'.$query : '');
            $route = Route::getRoutes()->match(Request::create($requestUri, 'GET'));
        } catch (HttpExceptionInterface|RoutingException) {
            return false;
        }

        $name = (string) $route->getName();

        if ($name === '') {
            return true;
        }

        return ! collect(self::PRIVATE_ROUTE_PREFIXES)
            ->contains(fn (string $prefix): bool => Str::startsWith($name, $prefix));
    }

    protected function isPrivatePath(string $path): bool
    {
        $firstSegment = Str::of($path)
            ->trim('/')
            ->before('/')
            ->lower()
            ->value();

        return in_array($firstSegment, self::PRIVATE_PATH_PREFIXES, true);
    }

    protected function validateFragment(string $attribute, string $fragment, Closure $fail): void
    {
        if ($fragment === '' || ! in_array($fragment, self::KNOWN_FRAGMENTS, true)) {
            $fail('The :attribute field must point to a known page section.');
        }
    }
}
