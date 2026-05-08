<?php

namespace App\Support;

use App\Models\Game;
use App\Queries\HomePageContentQuery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MarketplaceCatalogCache
{
    public function clear(): void
    {
        Cache::forget(HomePageContentQuery::CACHE_KEY);
        Cache::forget(HomePageContentQuery::CACHE_KEY.':all');

        foreach ($this->gameSlugs() as $slug) {
            Cache::forget(HomePageContentQuery::CACHE_KEY.':'.$slug);
        }
    }

    protected function gameSlugs(): array
    {
        try {
            if (! Schema::hasTable('games')) {
                return [];
            }

            return Game::query()
                ->pluck('slug')
                ->filter()
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }
}
