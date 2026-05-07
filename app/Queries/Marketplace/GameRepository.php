<?php

namespace App\Queries\Marketplace;

use App\Models\Game;
use App\Support\GameCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class GameRepository
{
    public function games(bool $includeDrafts = false): Collection
    {
        if (! $this->hasGamesTable()) {
            return collect();
        }

        try {
            $query = $this->baseQuery();

            if (! $includeDrafts) {
                $query->where('status', Game::STATUS_PUBLISHED);
            }

            return $query->get();
        } catch (Throwable) {
            return collect();
        }
    }

    public function activeGames(): Collection
    {
        return $this->games();
    }

    public function featuredGames(?int $limit = null): Collection
    {
        $games = $this->activeGames();
        $featured = $games
            ->filter(fn (Game $game): bool => (bool) data_get($game->metadata, 'featured', false))
            ->values();

        return $this->limited($featured->isNotEmpty() ? $featured : $games, $limit);
    }

    public function gamesByCategory(string $categorySlug, ?int $limit = null): Collection
    {
        $slug = $this->normalizeSlug($categorySlug);

        if ($slug === '') {
            return $this->limited($this->activeGames(), $limit);
        }

        $games = $this->activeGames()
            ->filter(fn (Game $game): bool => $this->normalizeSlug($game->category?->slug) === $slug)
            ->values();

        return $this->limited($games, $limit);
    }

    public function findActiveBySlug(mixed $slug): ?Game
    {
        return $this->findBySlug($slug, includeDrafts: false);
    }

    public function findBySlug(mixed $slug, bool $includeDrafts = true): ?Game
    {
        if (! $this->hasGamesTable()) {
            return null;
        }

        $gameSlug = $this->normalizeSlug($slug ?: GameCatalog::DEFAULT_GAME_SLUG);

        if ($gameSlug === '') {
            return null;
        }

        try {
            $query = $this->baseQuery()->where('slug', $gameSlug);

            if (! $includeDrafts) {
                $query->where('status', Game::STATUS_PUBLISHED);
            }

            return $query->first();
        } catch (Throwable) {
            return null;
        }
    }

    protected function baseQuery(): Builder
    {
        return Game::query()
            ->with($this->catalogRelations())
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    protected function catalogRelations(): array
    {
        return [
            'category',
            'seoMetadata',
            'pricingRules',
            'services.addons',
            'services.pricingRules',
            'services.seoMetadata',
            'ranks',
            'addons.pricingRules',
            'addons.services',
        ];
    }

    protected function limited(Collection $items, ?int $limit): Collection
    {
        return $limit === null ? $items->values() : $items->take($limit)->values();
    }

    protected function hasGamesTable(): bool
    {
        try {
            return Schema::hasTable('games');
        } catch (Throwable) {
            return false;
        }
    }

    protected function normalizeSlug(mixed $value): string
    {
        return Str::slug(trim((string) $value));
    }
}
