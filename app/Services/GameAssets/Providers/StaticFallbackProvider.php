<?php

namespace App\Services\GameAssets\Providers;

use App\Models\Game;
use App\Models\GameAsset;
use App\Models\GameAssetSyncLog;
use App\Services\GameAssets\AssetProviderInterface;
use App\Services\GameAssets\LocalAssetStore;

class StaticFallbackProvider implements AssetProviderInterface
{
    public function key(): string
    {
        return 'static_fallback';
    }

    public function supports(Game $game): bool
    {
        return true;
    }

    public function sync(Game $game, LocalAssetStore $store, bool $dryRun = false): array
    {
        $fallback = (string) config('game_asset_sources.fallbacks.card');

        if ($dryRun) {
            return [
                'status' => GameAssetSyncLog::STATUS_SUCCESS,
                'message' => 'Static fallback game card asset is available.',
                'counts' => ['cards' => 1],
            ];
        }

        $stored = $store->copyFallback($game, GameAsset::TYPE_CARD, 'fallback', $fallback);
        $asset = GameAsset::query()->updateOrCreate(
            ['game_id' => $game->id, 'asset_type' => GameAsset::TYPE_CARD, 'slug' => 'fallback'],
            [
                'label' => $game->name.' artwork fallback',
                'disk' => $store->disk(),
                'path' => $stored['path'],
                'source_type' => $this->key(),
                'source_name' => 'Local generated fallback',
                'source_license_notes' => 'Generated local SVG fallback for missing/uncleared game artwork.',
                'checksum' => $stored['checksum'],
                'width' => $stored['width'],
                'height' => $stored['height'],
                'alt_text' => $game->name.' game artwork',
                'metadata' => ['fallback' => true],
            ],
        );

        $assets = $game->assets ?? [];
        $assets['image'] = $asset->url();
        $assets['image_alt'] = $asset->alt_text;
        $game->forceFill(['assets' => $assets])->saveQuietly();

        return [
            'status' => GameAssetSyncLog::STATUS_SUCCESS,
            'message' => 'Static fallback game card asset cached locally.',
            'counts' => ['cards' => 1],
        ];
    }
}
