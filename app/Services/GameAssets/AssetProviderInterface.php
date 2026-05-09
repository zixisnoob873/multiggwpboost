<?php

namespace App\Services\GameAssets;

use App\Models\Game;

interface AssetProviderInterface
{
    public function key(): string;

    public function supports(Game $game): bool;

    /**
     * @return array{status:string,message:string,counts?:array<string,int|float|string>}
     */
    public function sync(Game $game, LocalAssetStore $store, bool $dryRun = false): array;
}
