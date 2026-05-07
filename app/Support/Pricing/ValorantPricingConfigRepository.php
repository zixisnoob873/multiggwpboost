<?php

namespace App\Support\Pricing;

use App\Models\PricingSetting;
use App\Models\PricingSettingRevision;
use App\Models\User;
use App\Support\BoostingCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ValorantPricingConfigRepository
{
    public const KEY = 'valorant';

    protected const CACHE_KEY = 'pricing-settings:valorant:v1';

    public function __construct(protected ValorantPricingConfigValidator $validator) {}

    public function current(): array
    {
        if (! $this->hasTable()) {
            return $this->fallbackSnapshot('missing_table');
        }

        try {
            return Cache::rememberForever(self::CACHE_KEY, function (): array {
                $setting = PricingSetting::query()
                    ->where('key', self::KEY)
                    ->first();

                if (! $setting instanceof PricingSetting || ! is_array($setting->config)) {
                    return $this->fallbackSnapshot('missing_database_config');
                }

                try {
                    $config = $this->validator->normalize($setting->config);
                } catch (Throwable $exception) {
                    Log::warning('Falling back to config/pricing.php after invalid database pricing config.', [
                        'key' => self::KEY,
                        'setting_id' => $setting->id,
                        'exception' => $exception::class,
                        'message' => $exception->getMessage(),
                    ]);

                    return $this->fallbackSnapshot('invalid_database_config');
                }

                return [
                    'config' => $config,
                    'version' => (int) $setting->version,
                    'checksum' => (string) ($setting->checksum ?: $this->validator->checksum($config)),
                    'source' => 'database',
                    'updatedAt' => optional($setting->updated_at)->toIso8601String(),
                ];
            });
        } catch (Throwable $exception) {
            Log::warning('Falling back to config/pricing.php after pricing settings read failure.', [
                'key' => self::KEY,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->fallbackSnapshot('read_failure');
        }
    }

    public function config(): array
    {
        return $this->current()['config'];
    }

    public function publicPayload(): array
    {
        $snapshot = $this->current();
        $config = $snapshot['config'];

        return [
            'version' => $snapshot['version'],
            'checksum' => $snapshot['checksum'],
            'source' => $snapshot['source'],
            'updatedAt' => $snapshot['updatedAt'],
            'pricingPreview' => [
                'version' => $snapshot['version'],
                'checksum' => $snapshot['checksum'],
                'source' => $snapshot['source'],
                'updatedAt' => $snapshot['updatedAt'],
                'rankOrder' => $config['rank_order'] ?? [],
                'services' => $config['services'] ?? [],
                'basePrices' => $config['base_prices'] ?? [],
                'specialRankBoostSteps' => $config['special_rank_boost_steps'] ?? [],
                'rrRules' => $config['rr_rules'] ?? [],
                'addons' => $config['addons'] ?? [],
                'modifiers' => $config['modifiers'] ?? [],
                'labels' => $config['labels'] ?? [],
            ],
        ];
    }

    public function update(array $config, ?User $actor = null, string $action = 'update', array $metadata = []): PricingSetting
    {
        $normalized = $this->validator->normalize($config);
        $checksum = $this->validator->checksum($normalized);

        $setting = DB::transaction(function () use ($normalized, $checksum, $actor, $action, $metadata): PricingSetting {
            $setting = PricingSetting::query()
                ->where('key', self::KEY)
                ->lockForUpdate()
                ->first();
            $version = ((int) ($setting?->version ?? 0)) + 1;

            if (! $setting instanceof PricingSetting) {
                $setting = new PricingSetting(['key' => self::KEY]);
            }

            $setting->forceFill([
                'config' => $normalized,
                'version' => $version,
                'checksum' => $checksum,
                'updated_by' => $actor?->getKey(),
            ])->save();

            PricingSettingRevision::query()->create([
                'pricing_setting_id' => $setting->getKey(),
                'key' => self::KEY,
                'action' => $action,
                'version' => $version,
                'checksum' => $checksum,
                'config' => $normalized,
                'actor_id' => $actor?->getKey(),
                'metadata' => $metadata,
                'created_at' => now(),
            ]);

            return $setting->refresh();
        }, 3);

        $this->forget();

        return $setting;
    }

    public function resetToDefaults(?User $actor = null, array $metadata = []): PricingSetting
    {
        return $this->update($this->defaults(), $actor, 'reset', $metadata);
    }

    public function seedDefaults(): ?PricingSetting
    {
        if (! $this->hasTable()) {
            return null;
        }

        $existing = PricingSetting::query()
            ->where('key', self::KEY)
            ->first();

        if ($existing instanceof PricingSetting) {
            return $existing;
        }

        return $this->update($this->defaults(), null, 'seed', [
            'source' => 'config/pricing.php',
        ]);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        BoostingCatalog::flushRuntimeCaches();
    }

    public function defaults(): array
    {
        return $this->validator->normalize((array) config('pricing', []));
    }

    protected function fallbackSnapshot(string $source): array
    {
        $config = $this->defaults();

        return [
            'config' => $config,
            'version' => 0,
            'checksum' => $this->validator->checksum($config),
            'source' => $source,
            'updatedAt' => null,
        ];
    }

    protected function hasTable(): bool
    {
        try {
            return Schema::hasTable('pricing_settings');
        } catch (Throwable) {
            return false;
        }
    }
}
