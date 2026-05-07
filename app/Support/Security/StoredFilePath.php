<?php

namespace App\Support\Security;

use Illuminate\Support\Str;

class StoredFilePath
{
    /**
     * @param  array<int, string>|string  $allowedPrefixes
     */
    public static function clean(mixed $storedPath, array|string $allowedPrefixes): ?string
    {
        $path = trim((string) $storedPath);

        if ($path === '') {
            return null;
        }

        $normalized = str_replace('\\', '/', $path);

        if (
            $normalized !== $path
            || str_contains($normalized, '..')
            || str_starts_with($normalized, '/')
            || preg_match('/^[A-Za-z]:\//', $normalized) === 1
            || preg_match('/^(?:[a-z][a-z0-9+.-]*:)?\/\//i', $normalized) === 1
        ) {
            return null;
        }

        $prefixes = is_array($allowedPrefixes) ? $allowedPrefixes : [$allowedPrefixes];

        return Str::startsWith($normalized, $prefixes) ? $normalized : null;
    }
}
