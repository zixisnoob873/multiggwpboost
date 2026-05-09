<?php

namespace App\Services\GameAssets;

use App\Models\Game;
use App\Models\GameAssetSyncLog;
use Illuminate\Support\Arr;
use Throwable;

class GameAssetSyncService
{
    /** @var array<int, AssetProviderInterface> */
    private array $providers;

    public function __construct(private readonly LocalAssetStore $store)
    {
        $this->providers = collect(config('game_asset_sources.providers', []))
            ->map(fn (string $providerClass): AssetProviderInterface => app($providerClass))
            ->all();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function sync(?string $gameSlug = null, ?string $providerKey = null, bool $dryRun = false): array
    {
        $games = Game::query()
            ->when($gameSlug, fn ($query) => $query->where('slug', $gameSlug))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $results = [];

        foreach ($games as $game) {
            foreach ($this->providers as $provider) {
                if ($providerKey && $provider->key() !== $providerKey) {
                    continue;
                }

                if (! $provider->supports($game)) {
                    continue;
                }

                $results[] = $this->runProvider($game, $provider, $dryRun);
            }
        }

        return $results;
    }

    /** @return array<string,mixed> */
    private function runProvider(Game $game, AssetProviderInterface $provider, bool $dryRun): array
    {
        $startedAt = now();
        $payload = [
            'game_id' => $game->id,
            'provider' => $provider->key(),
            'status' => GameAssetSyncLog::STATUS_FAILED,
            'started_at' => $startedAt,
            'context' => ['dry_run' => $dryRun],
        ];

        try {
            $result = $provider->sync($game, $this->store, $dryRun);
            $payload = array_merge($payload, [
                'status' => Arr::get($result, 'status', GameAssetSyncLog::STATUS_SUCCESS),
                'message' => Arr::get($result, 'message'),
                'counts' => Arr::get($result, 'counts', []),
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $payload = array_merge($payload, [
                'status' => GameAssetSyncLog::STATUS_FAILED,
                'message' => $exception->getMessage(),
                'counts' => [],
                'finished_at' => now(),
            ]);
        }

        if (! $dryRun) {
            GameAssetSyncLog::query()->create($payload);
        }

        return [
            'game' => $game->slug,
            'provider' => $provider->key(),
            'status' => $payload['status'],
            'message' => $payload['message'] ?? null,
            'counts' => $payload['counts'] ?? [],
        ];
    }
}
