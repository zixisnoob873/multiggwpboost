<?php

namespace App\Queries\Marketplace;

use App\Models\Game;
use App\Models\GameService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class ServiceRepository
{
    public function __construct(
        protected GameRepository $games,
    ) {}

    public function servicesByGameSlug(mixed $gameSlug): Collection
    {
        $game = $this->games->findActiveBySlug($gameSlug);

        if (! $game instanceof Game || ! $this->hasGameServicesTable()) {
            return collect();
        }

        try {
            return $this->baseQuery()
                ->where('game_id', $game->id)
                ->where('status', Game::STATUS_PUBLISHED)
                ->get();
        } catch (Throwable) {
            return collect();
        }
    }

    public function servicePageLookup(mixed $gameSlug, mixed $serviceSlug): ?GameService
    {
        $game = $this->games->findActiveBySlug($gameSlug);

        if (! $game instanceof Game || ! $this->hasGameServicesTable()) {
            return null;
        }

        $slug = $this->normalizeSlug($serviceSlug);

        if ($slug === '') {
            return null;
        }

        try {
            return $this->baseQuery()
                ->where('game_id', $game->id)
                ->where('slug', $slug)
                ->where('status', Game::STATUS_PUBLISHED)
                ->first();
        } catch (Throwable) {
            return null;
        }
    }

    public function popularServices(?int $limit = null): Collection
    {
        return $this->flaggedOrSorted('popular', $limit);
    }

    public function homepageFeaturedServices(?int $limit = null): Collection
    {
        return $this->flaggedOrSorted('homepage_featured', $limit);
    }

    public function publishedCatalogServices(?int $limit = null): Collection
    {
        $services = $this->publishedServices();

        return $limit === null ? $services : $services->take($limit)->values();
    }

    public function relatedServices(GameService $service, int $limit = 4): Collection
    {
        if (! $this->hasGameServicesTable()) {
            return collect();
        }

        $relatedSlugs = collect(data_get($service->metadata, 'related_services', []))
            ->map(fn (mixed $slug): string => $this->normalizeSlug($slug))
            ->filter()
            ->unique()
            ->values();

        $services = $this->publishedServices()
            ->filter(fn (GameService $candidate): bool => (int) $candidate->game_id === (int) $service->game_id)
            ->reject(fn (GameService $candidate): bool => (int) $candidate->id === (int) $service->id)
            ->values();

        if ($relatedSlugs->isEmpty()) {
            return $services->take($limit)->values();
        }

        $related = $services
            ->filter(fn (GameService $candidate): bool => $relatedSlugs->contains($candidate->slug))
            ->sortBy(fn (GameService $candidate): int => $relatedSlugs->search($candidate->slug))
            ->values();

        if ($related->count() >= $limit) {
            return $related->take($limit)->values();
        }

        return $related
            ->merge($services->reject(fn (GameService $candidate): bool => $related->contains('id', $candidate->id)))
            ->take($limit)
            ->values();
    }

    public function findComparableServiceId(mixed $gameSlug, mixed $service): ?int
    {
        $needle = $this->normalizeComparable($service);

        if ($needle === '') {
            return null;
        }

        return $this->servicesByGameSlug($gameSlug)
            ->first(function (GameService $candidate) use ($needle): bool {
                return in_array($needle, [
                    $this->normalizeComparable($candidate->slug),
                    $this->normalizeComparable($candidate->name),
                    $this->normalizeComparable($candidate->kind),
                ], true);
            })?->id;
    }

    protected function flaggedOrSorted(string $metadataFlag, ?int $limit = null): Collection
    {
        $services = $this->publishedServices();
        $flagged = $services
            ->filter(fn (GameService $service): bool => (bool) data_get($service->metadata, $metadataFlag, false))
            ->values();

        $items = $flagged->isNotEmpty() ? $flagged : $services;

        return $limit === null ? $items : $items->take($limit)->values();
    }

    protected function publishedServices(): Collection
    {
        if (! $this->hasGameServicesTable()) {
            return collect();
        }

        try {
            return $this->baseQuery()
                ->where('game_services.status', Game::STATUS_PUBLISHED)
                ->whereHas('game', fn (Builder $query): Builder => $query->where('status', Game::STATUS_PUBLISHED))
                ->get();
        } catch (Throwable) {
            return collect();
        }
    }

    protected function baseQuery(): Builder
    {
        return GameService::query()
            ->with($this->catalogRelations())
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    protected function catalogRelations(): array
    {
        return [
            'game.category',
            'game.seoMetadata',
            'seoMetadata',
            'pricingRules',
            'addons',
            'faqs',
            'reviews',
        ];
    }

    protected function hasGameServicesTable(): bool
    {
        try {
            return Schema::hasTable('game_services');
        } catch (Throwable) {
            return false;
        }
    }

    protected function normalizeSlug(mixed $value): string
    {
        return Str::slug(trim((string) $value));
    }

    protected function normalizeComparable(mixed $value): string
    {
        return Str::of((string) $value)
            ->lower()
            ->replace('_', '-')
            ->replaceMatches('/[()+$%]/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();
    }
}
