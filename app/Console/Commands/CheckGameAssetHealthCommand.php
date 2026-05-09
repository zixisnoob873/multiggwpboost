<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\GameAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CheckGameAssetHealthCommand extends Command
{
    protected $signature = 'game-assets:health {--json : Output JSON}';

    protected $description = 'Report missing or broken cached game asset records.';

    public function handle(): int
    {
        if (! Schema::hasTable('game_assets')) {
            $this->warn('game_assets table does not exist. Run migrations first.');
            return self::SUCCESS;
        }

        $rows = Game::query()->orderBy('name')->get()->map(function (Game $game): array {
            $assets = GameAsset::query()->where('game_id', $game->id)->get();
            $missingFiles = $assets
                ->filter(fn (GameAsset $asset) => filled($asset->path) && ! Storage::disk($asset->disk ?: 'public')->exists($asset->path))
                ->map(fn (GameAsset $asset) => $asset->asset_type.':'.$asset->slug)
                ->values()
                ->all();

            return [
                'game' => $game->slug,
                'assets' => $assets->count(),
                'characters' => method_exists($game, 'characters') ? $game->characters()->count() : 0,
                'missing_files' => $missingFiles,
            ];
        })->values();

        if ($this->option('json')) {
            $this->line(json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $this->table(['Game', 'Assets', 'Characters', 'Missing files'], $rows->map(fn ($row) => [
            $row['game'],
            $row['assets'],
            $row['characters'],
            implode(', ', $row['missing_files']) ?: '-',
        ]));

        return $rows->contains(fn ($row) => $row['missing_files'] !== []) ? self::FAILURE : self::SUCCESS;
    }
}
