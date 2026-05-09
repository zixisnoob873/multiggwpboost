<?php

namespace App\Services\GameAssets\Providers;

use App\Models\Game;
use App\Models\GameAsset;
use App\Models\GameAssetSyncLog;
use App\Services\GameAssets\AssetProviderInterface;
use App\Services\GameAssets\LocalAssetStore;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RiotDataDragonProvider implements AssetProviderInterface
{
    public function __construct(private readonly ?HttpFactory $httpFactory = null)
    {
    }

    public function key(): string
    {
        return 'riot_data_dragon';
    }

    public function supports(Game $game): bool
    {
        return in_array($game->slug, ['league-of-legends', 'tft'], true);
    }

    public function sync(Game $game, LocalAssetStore $store, bool $dryRun = false): array
    {
        $version = $this->latestVersion();

        if (! $version) {
            return [
                'status' => GameAssetSyncLog::STATUS_SKIPPED,
                'message' => 'Data Dragon version could not be resolved.',
                'counts' => [],
            ];
        }

        if ($game->slug === 'tft') {
            return [
                'status' => GameAssetSyncLog::STATUS_SKIPPED,
                'message' => 'Data Dragon version resolved; TFT asset taxonomy requires project-specific set mapping before automated character sync.',
                'counts' => ['versions' => 1],
            ];
        }

        $client = $this->httpFactory ?: Http::getFacadeRoot();
        $championResponse = $client->timeout(10)->get("https://ddragon.leagueoflegends.com/cdn/{$version}/data/en_US/champion.json");

        if (! $championResponse->successful()) {
            return [
                'status' => GameAssetSyncLog::STATUS_SKIPPED,
                'message' => 'Data Dragon champion catalog request failed.',
                'counts' => [],
            ];
        }

        $champions = collect($championResponse->json('data', []));
        $allowedHosts = config("game_asset_sources.games.{$game->slug}.allowed_hosts", ['ddragon.leagueoflegends.com']);
        $count = 0;

        foreach ($champions->take((int) config('game_asset_sources.limit', 25)) as $champion) {
            $name = (string) ($champion['name'] ?? $champion['id'] ?? 'Champion');
            $slug = Str::slug($name);
            $imageName = (string) ($champion['image']['full'] ?? '');

            if ($imageName === '') {
                continue;
            }

            $sourceUrl = "https://ddragon.leagueoflegends.com/cdn/{$version}/img/champion/{$imageName}";

            if ($dryRun) {
                $count++;
                continue;
            }

            $stored = $store->download($game, $sourceUrl, GameAsset::TYPE_CHARACTER_PORTRAIT, $slug, $allowedHosts);
            $asset = GameAsset::query()->updateOrCreate(
                ['game_id' => $game->id, 'asset_type' => GameAsset::TYPE_CHARACTER_PORTRAIT, 'slug' => $slug],
                [
                    'label' => $name,
                    'disk' => $store->disk(),
                    'path' => $stored['path'],
                    'source_url' => $sourceUrl,
                    'source_type' => $this->key(),
                    'source_name' => 'Riot Data Dragon',
                    'source_license_notes' => 'Use subject to Riot Developer/API terms and product registration.',
                    'checksum' => $stored['checksum'],
                    'width' => $stored['width'],
                    'height' => $stored['height'],
                    'alt_text' => $name.' champion portrait',
                    'metadata' => ['version' => $version, 'riot_id' => $champion['id'] ?? null],
                ],
            );

            $game->characters()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'role' => is_array($champion['tags'] ?? null) ? implode(', ', $champion['tags']) : null,
                    'portrait_asset_id' => $asset->id,
                    'source_id' => $champion['id'] ?? null,
                    'source_type' => $this->key(),
                    'metadata' => ['version' => $version],
                ],
            );
            $count++;
        }

        return [
            'status' => GameAssetSyncLog::STATUS_SUCCESS,
            'message' => 'Data Dragon champion portraits synced and cached locally.',
            'counts' => ['characters' => $count],
        ];
    }

    private function latestVersion(): ?string
    {
        $client = $this->httpFactory ?: Http::getFacadeRoot();
        $response = $client->timeout(10)->get('https://ddragon.leagueoflegends.com/api/versions.json');

        if (! $response->successful()) {
            return null;
        }

        $versions = $response->json();

        return is_array($versions) ? (string) ($versions[0] ?? '') ?: null : null;
    }
}
