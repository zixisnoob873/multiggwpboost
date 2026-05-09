<?php

namespace App\Services\GameAssets;

use App\Models\Game;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class LocalAssetStore
{
    public function __construct(private readonly HttpFactory $http)
    {
    }

    public function disk(): string
    {
        return (string) config('game_asset_sources.disk', 'public');
    }

    /**
     * @return array{path:string,checksum:string,width:?int,height:?int,mime:string}
     */
    public function download(Game $game, string $url, string $assetType, string $slug, array $allowedHosts = []): array
    {
        $this->assertAllowedUrl($url, $allowedHosts);

        $response = $this->http->timeout(20)->retry(2, 600)->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("Asset request failed for {$url}");
        }

        $body = $response->body();
        $maxBytes = (int) config('game_asset_sources.max_bytes', 3145728);

        if ($body === '' || strlen($body) > $maxBytes) {
            throw new RuntimeException('Remote asset is empty or too large.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->buffer($body);
        $allowed = config('game_asset_sources.allowed_mime_types', []);

        if (! isset($allowed[$mime])) {
            throw new RuntimeException("Unsupported remote asset MIME type {$mime}.");
        }

        $imageSize = @getimagesizefromstring($body) ?: [null, null];
        $extension = $allowed[$mime];
        $safeSlug = Str::slug($slug) ?: 'asset';
        $path = sprintf('game-assets/%s/%s/%s.%s', $game->slug, $assetType, $safeSlug, $extension);

        Storage::disk($this->disk())->put($path, $body);

        return [
            'path' => $path,
            'checksum' => hash('sha256', $body),
            'width' => is_numeric($imageSize[0] ?? null) ? (int) $imageSize[0] : null,
            'height' => is_numeric($imageSize[1] ?? null) ? (int) $imageSize[1] : null,
            'mime' => $mime,
        ];
    }

    /**
     * @return array{path:string,checksum:string,width:?int,height:?int,mime:string}
     */
    public function copyFallback(Game $game, string $assetType, string $slug, string $sourcePath): array
    {
        if (! is_file($sourcePath)) {
            throw new InvalidArgumentException("Fallback asset not found at {$sourcePath}");
        }

        $body = (string) file_get_contents($sourcePath);
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'svg';
        $path = sprintf('game-assets/%s/%s/%s.%s', $game->slug, $assetType, Str::slug($slug) ?: 'fallback', $extension);
        Storage::disk($this->disk())->put($path, $body);

        $imageSize = $extension === 'svg' ? [null, null] : (@getimagesizefromstring($body) ?: [null, null]);

        return [
            'path' => $path,
            'checksum' => hash('sha256', $body),
            'width' => is_numeric($imageSize[0] ?? null) ? (int) $imageSize[0] : null,
            'height' => is_numeric($imageSize[1] ?? null) ? (int) $imageSize[1] : null,
            'mime' => $extension === 'svg' ? 'image/svg+xml' : 'application/octet-stream',
        ];
    }

    private function assertAllowedUrl(string $url, array $allowedHosts): void
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            throw new InvalidArgumentException('Remote asset URL is invalid.');
        }

        $host = Str::lower($host);
        $allowedHosts = array_map(fn ($value) => Str::lower((string) $value), $allowedHosts);

        if ($allowedHosts === [] || ! in_array($host, $allowedHosts, true)) {
            throw new InvalidArgumentException("Remote asset host {$host} is not allow-listed.");
        }

        if (! str_starts_with($url, 'https://')) {
            throw new InvalidArgumentException('Remote asset URL must use HTTPS.');
        }
    }
}
