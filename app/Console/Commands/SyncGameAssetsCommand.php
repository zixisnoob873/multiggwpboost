<?php

namespace App\Console\Commands;

use App\Services\GameAssets\GameAssetSyncService;
use Illuminate\Console\Command;

class SyncGameAssetsCommand extends Command
{
    protected $signature = 'game-assets:sync {game? : Optional game slug} {--provider= : Provider key} {--dry-run : Report without writing}';

    protected $description = 'Sync and cache configured game artwork, ranks, and character assets server-side.';

    public function handle(GameAssetSyncService $syncService): int
    {
        $results = $syncService->sync(
            $this->argument('game') ?: null,
            $this->option('provider') ?: null,
            (bool) $this->option('dry-run'),
        );

        if ($results === []) {
            $this->warn('No matching game asset providers were run.');
            return self::SUCCESS;
        }

        foreach ($results as $result) {
            $line = sprintf(
                '[%s] %s via %s: %s',
                strtoupper((string) ($result['status'] ?? 'unknown')),
                $result['game'] ?? 'all',
                $result['provider'] ?? 'provider',
                $result['message'] ?? 'complete',
            );

            match ($result['status'] ?? null) {
                'failed' => $this->error($line),
                'skipped' => $this->warn($line),
                default => $this->info($line),
            };
        }

        return collect($results)->contains(fn ($result) => ($result['status'] ?? null) === 'failed')
            ? self::FAILURE
            : self::SUCCESS;
    }
}
