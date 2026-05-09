<?php

namespace App\Support\Media;

use App\Models\Game;
use App\Models\GameAsset;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

class GameAssetResolver
{
    private static ?bool $hasAssetTable = null;

    public function gameCardImage(Game $game): ?string
    {
        $pipelineUrl = $this->pipelineAssetUrl($game, GameAsset::TYPE_CARD)
            ?? $this->pipelineAssetUrl($game, GameAsset::TYPE_BACKGROUND);

        if ($pipelineUrl) {
            return $pipelineUrl;
        }

        $assets = $game->assets ?? [];

        return Arr::get($assets, 'image_url')
            ?: Arr::get($assets, 'image')
            ?: asset('assets/game-assets/fallbacks/game-card.svg');
    }

    public function gameAltText(Game $game): string
    {
        $assets = $game->assets ?? [];

        return (string) (Arr::get($assets, 'image_alt') ?: $game->name.' artwork');
    }

    private function pipelineAssetUrl(Game $game, string $type): ?string
    {
        self::$hasAssetTable ??= Schema::hasTable('game_assets');

        if (! self::$hasAssetTable) {
            return null;
        }

        $asset = GameAsset::query()
            ->where('game_id', $game->id)
            ->where('asset_type', $type)
            ->whereNotNull('path')
            ->orderByRaw("case when slug = 'fallback' then 1 else 0 end")
            ->first();

        return $asset?->url();
    }
}
