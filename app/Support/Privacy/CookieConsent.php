<?php

namespace App\Support\Privacy;

use Illuminate\Http\Request;

class CookieConsent
{
    public const COOKIE_NAME = 'ggwp_cookie_consent';
    public const VERSION = 1;

    public const CATEGORY_NECESSARY = 'necessary';
    public const CATEGORY_ANALYTICS = 'analytics';
    public const CATEGORY_SUPPORT = 'support';

    /**
     * @return array{version:int,timestamp:string,categories:array{necessary:bool,analytics:bool,support:bool}}|null
     */
    public static function fromRequest(Request $request): ?array
    {
        $rawConsent = $request->cookie(self::COOKIE_NAME);

        if (! is_string($rawConsent) || trim($rawConsent) === '') {
            return null;
        }

        $decodedConsent = rawurldecode($rawConsent);
        $payload = json_decode($decodedConsent, true);

        if (! is_array($payload)) {
            return null;
        }

        $version = (int) ($payload['version'] ?? 0);
        $categories = is_array($payload['categories'] ?? null) ? $payload['categories'] : [];

        return [
            'version' => $version,
            'timestamp' => is_string($payload['timestamp'] ?? null) ? $payload['timestamp'] : '',
            'categories' => [
                self::CATEGORY_NECESSARY => true,
                self::CATEGORY_ANALYTICS => (bool) ($categories[self::CATEGORY_ANALYTICS] ?? false),
                self::CATEGORY_SUPPORT => (bool) ($categories[self::CATEGORY_SUPPORT] ?? false),
            ],
        ];
    }

    public static function isCurrent(?array $consent): bool
    {
        return is_array($consent)
            && (int) ($consent['version'] ?? 0) === self::VERSION
            && is_array($consent['categories'] ?? null);
    }

    public static function shouldShowBanner(?array $consent): bool
    {
        return ! self::isCurrent($consent);
    }

    public static function allows(?array $consent, string $category): bool
    {
        if ($category === self::CATEGORY_NECESSARY) {
            return true;
        }

        if (! self::isCurrent($consent)) {
            return false;
        }

        return (bool) ($consent['categories'][$category] ?? false);
    }
}
