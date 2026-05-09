<?php

namespace App\Services\GameAssets\Providers;

use App\Models\Game;
use App\Models\GameAssetSyncLog;
use App\Services\GameAssets\AssetProviderInterface;
use App\Services\GameAssets\LocalAssetStore;

class ValorantContentProvider implements AssetProviderInterface
{
    public function key(): string
    {
        return 'riot_val_content';
    }

    public function supports(Game $game): bool
    {
        return $game->slug === 'valorant';
    }

    public function sync(Game $game, LocalAssetStore $store, bool $dryRun = false): array
    {
        $apiKey = (string) config('services.riot.key', env('RIOT_API_KEY', ''));

        if ($apiKey === '') {
            return [
                'status' => GameAssetSyncLog::STATUS_SKIPPED,
                'message' => 'Riot API key is not configured. Keep Valorant on local curated/fallback assets.',
                'counts' => [],
            ];
        }

        return [
            'status' => GameAssetSyncLog::STATUS_SKIPPED,
            'message' => 'Valorant official/public content source is documented; implement registered-app regional fetch after production key approval and license review.',
            'counts' => ['api_key_present' => 1],
        ];
    }
}
