<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SystemSettingService
{
    public const MAINTENANCE_MODE_KEY = 'maintenance_mode';

    protected const CACHE_PREFIX = 'system-setting:';

    public function isMaintenanceModeEnabled(): bool
    {
        return $this->getBoolean(self::MAINTENANCE_MODE_KEY, false);
    }

    public function setMaintenanceMode(bool $enabled): bool
    {
        return $this->putBoolean(self::MAINTENANCE_MODE_KEY, $enabled);
    }

    public function getBoolean(string $key, bool $default = false): bool
    {
        $cacheKey = $this->cacheKey($key);

        try {
            return (bool) Cache::rememberForever($cacheKey, function () use ($key, $default): bool {
                $setting = SystemSetting::query()
                    ->where('key', $key)
                    ->first();

                if (! $setting instanceof SystemSetting) {
                    return $default;
                }

                return $this->normalizeBoolean($setting->value, $default);
            });
        } catch (\Throwable $exception) {
            Log::warning('Falling back to the default system setting value after a read failure.', [
                'key' => $key,
                'default' => $default,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $default;
        }
    }

    public function putBoolean(string $key, bool $value): bool
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value ? '1' : '0']
        );

        Cache::forever($this->cacheKey($key), $value);

        return $value;
    }

    public function getString(string $key, ?string $default = null): ?string
    {
        $cacheKey = $this->cacheKey($key);

        try {
            return Cache::rememberForever($cacheKey, function () use ($key, $default): ?string {
                $setting = SystemSetting::query()
                    ->where('key', $key)
                    ->first();

                if (! $setting instanceof SystemSetting) {
                    return $default;
                }

                $value = trim((string) $setting->value);

                return $value !== '' ? $value : $default;
            });
        } catch (\Throwable $exception) {
            Log::warning('Falling back to the default system setting string after a read failure.', [
                'key' => $key,
                'default' => $default,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $default;
        }
    }

    public function putString(string $key, ?string $value): ?string
    {
        $normalized = trim((string) $value);

        SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $normalized !== '' ? $normalized : null]
        );

        Cache::forever($this->cacheKey($key), $normalized !== '' ? $normalized : null);

        return $normalized !== '' ? $normalized : null;
    }

    public function getMany(array $definitions): array
    {
        $values = [];

        foreach ($definitions as $key => $definition) {
            $values[$key] = $this->getString($key, $definition['default'] ?? null);
        }

        return $values;
    }

    public function forget(string $key): void
    {
        Cache::forget($this->cacheKey($key));
    }

    protected function normalizeBoolean(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $normalized ?? $default;
    }

    protected function cacheKey(string $key): string
    {
        return self::CACHE_PREFIX.$key;
    }
}
