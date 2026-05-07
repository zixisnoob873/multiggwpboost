<?php

namespace App\Support\Pricing;

use App\Models\PricingSetting;
use App\Models\PricingSettingRevision;
use App\Models\User;
use App\Support\BoostingCatalog;
use App\Support\GameCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ValorantPricingConfigRepository
{
    public const KEY = 'valorant';

    protected const CACHE_PREFIX = 'pricing-settings';

    public function __construct(
        protected ValorantPricingConfigValidator $validator,
        protected GameCatalog $gameCatalog,
    ) {}

    public function current(?string $gameSlug = null): array
    {
        $key = $this->key($gameSlug);

        if (! $this->hasTable()) {
            return $this->fallbackSnapshot('missing_table', $key);
        }

        try {
            return Cache::rememberForever($this->cacheKey($key), function () use ($key): array {
                $setting = PricingSetting::query()
                    ->where('key', $key)
                    ->first();

                if (! $setting instanceof PricingSetting || ! is_array($setting->config)) {
                    return $this->fallbackSnapshot('missing_database_config', $key);
                }

                try {
                    $config = $this->validator->normalize($setting->config, $key);
                } catch (Throwable $exception) {
                    Log::warning('Falling back to config/pricing.php after invalid database pricing config.', [
                        'key' => $key,
                        'setting_id' => $setting->id,
                        'exception' => $exception::class,
                        'message' => $exception->getMessage(),
                    ]);

                    return $this->fallbackSnapshot('invalid_database_config', $key);
                }

                return [
                    'key' => $key,
                    'gameSlug' => $key,
                    'game' => $this->gameCatalog->game($key),
                    'config' => $config,
                    'version' => (int) $setting->version,
                    'checksum' => (string) ($setting->checksum ?: $this->validator->checksum($config)),
                    'source' => 'database',
                    'updatedAt' => optional($setting->updated_at)->toIso8601String(),
                ];
            });
        } catch (Throwable $exception) {
            Log::warning('Falling back to config/pricing.php after pricing settings read failure.', [
                'key' => $key,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->fallbackSnapshot('read_failure', $key);
        }
    }

    public function config(?string $gameSlug = null): array
    {
        return $this->current($gameSlug)['config'];
    }

    public function publicPayload(?string $gameSlug = null): array
    {
        $snapshot = $this->current($gameSlug);
        $config = $snapshot['config'];
        $game = $snapshot['game'] ?? $this->gameCatalog->game($snapshot['gameSlug'] ?? self::KEY);

        return [
            'key' => $snapshot['key'],
            'gameSlug' => $snapshot['gameSlug'],
            'game' => $game,
            'version' => $snapshot['version'],
            'checksum' => $snapshot['checksum'],
            'source' => $snapshot['source'],
            'updatedAt' => $snapshot['updatedAt'],
            'pricingPreview' => [
                'gameSlug' => $snapshot['gameSlug'],
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

    public function update(array $config, ?User $actor = null, string $action = 'update', array $metadata = [], ?string $gameSlug = null): PricingSetting
    {
        $key = $this->key($gameSlug);
        $normalized = $this->validator->normalize($config, $key);
        $checksum = $this->validator->checksum($normalized);

        $setting = DB::transaction(function () use ($key, $normalized, $checksum, $actor, $action, $metadata): PricingSetting {
            $setting = PricingSetting::query()
                ->where('key', $key)
                ->lockForUpdate()
                ->first();
            $version = ((int) ($setting?->version ?? 0)) + 1;

            if (! $setting instanceof PricingSetting) {
                $setting = new PricingSetting(['key' => $key]);
            }

            $attributes = [
                'config' => $normalized,
                'version' => $version,
                'checksum' => $checksum,
                'updated_by' => $actor?->getKey(),
            ];

            if ($this->pricingSettingsHaveGameId()) {
                $attributes['game_id'] = $this->gameCatalog->gameId($key);
            }

            $setting->forceFill($attributes)->save();

            $revision = [
                'pricing_setting_id' => $setting->getKey(),
                'key' => $key,
                'action' => $action,
                'version' => $version,
                'checksum' => $checksum,
                'config' => $normalized,
                'actor_id' => $actor?->getKey(),
                'metadata' => $metadata,
                'created_at' => now(),
            ];

            if ($this->pricingSettingRevisionsHaveGameId()) {
                $revision['game_id'] = $this->gameCatalog->gameId($key);
            }

            PricingSettingRevision::query()->create($revision);

            return $setting->refresh();
        }, 3);

        $this->forget($key);

        return $setting;
    }

    public function resetToDefaults(?User $actor = null, array $metadata = [], ?string $gameSlug = null): PricingSetting
    {
        return $this->update($this->defaults($gameSlug), $actor, 'reset', $metadata, $gameSlug);
    }

    public function seedDefaults(?string $gameSlug = null): ?PricingSetting
    {
        $key = $this->key($gameSlug);

        if (! $this->hasTable()) {
            return null;
        }

        $existing = PricingSetting::query()
            ->where('key', $key)
            ->first();

        if ($existing instanceof PricingSetting) {
            return $existing;
        }

        return $this->update($this->defaults($key), null, 'seed', [
            'source' => 'config/pricing.php',
        ], $key);
    }

    public function forget(?string $gameSlug = null): void
    {
        Cache::forget($this->cacheKey($this->key($gameSlug)));
        BoostingCatalog::flushRuntimeCaches();
    }

    public function defaults(?string $gameSlug = null): array
    {
        return $this->validator->normalize((array) config('pricing', []), $this->key($gameSlug));
    }

    protected function fallbackSnapshot(string $source, ?string $gameSlug = null): array
    {
        $key = $this->key($gameSlug);
        $config = $this->defaults($key);

        return [
            'key' => $key,
            'gameSlug' => $key,
            'game' => $this->gameCatalog->game($key),
            'config' => $config,
            'version' => 0,
            'checksum' => $this->validator->checksum($config),
            'source' => $source,
            'updatedAt' => null,
        ];
    }

    protected function key(?string $gameSlug = null): string
    {
        return $this->gameCatalog->normalizeSlug($gameSlug ?? self::KEY);
    }

    protected function cacheKey(string $key): string
    {
        return self::CACHE_PREFIX.":{$key}:v1";
    }

    protected function hasTable(): bool
    {
        try {
            return Schema::hasTable('pricing_settings');
        } catch (Throwable) {
            return false;
        }
    }

    protected function pricingSettingsHaveGameId(): bool
    {
        try {
            return Schema::hasColumn('pricing_settings', 'game_id');
        } catch (Throwable) {
            return false;
        }
    }

    protected function pricingSettingRevisionsHaveGameId(): bool
    {
        try {
            return Schema::hasColumn('pricing_setting_revisions', 'game_id');
        } catch (Throwable) {
            return false;
        }
    }
}
