<?php

namespace App\Queries;

use App\Models\BlogArticle;
use App\Models\Faq;
use App\Models\FeaturedBooster;
use App\Models\Promotion;
use App\Models\Review;
use App\Queries\Marketplace\GameRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class HomePageContentQuery
{
    public const CACHE_KEY = 'home-page-content.v7';

    public function __construct(
        protected GameRepository $games,
    ) {}

    public function execute(?string $gameSlug = null): array
    {
        $cacheKey = self::CACHE_KEY.':'.($gameSlug === null ? 'all' : $gameSlug);
        $content = Cache::remember($cacheKey, now()->addMinutes(5), fn (): array => [
            'faqs' => $this->faqs($gameSlug),
            'featuredBoosters' => $this->featuredBoosters(),
            'promotions' => $this->promotions(),
            'reviews' => $this->reviews($gameSlug),
        ]);

        $content['latestBlogArticles'] = $this->latestBlogArticles($gameSlug);

        return $content;
    }

    protected function faqs(?string $gameSlug = null)
    {
        if (! Schema::hasTable('faqs')) {
            return collect();
        }

        $query = Faq::query()->orderBy('order');

        $this->scopeToGame($query, $gameSlug);

        return $query->get(['question', 'answer']);
    }

    protected function featuredBoosters()
    {
        if (! Schema::hasTable('featured_boosters')) {
            return collect();
        }

        return FeaturedBooster::orderBy('sort_order')->get();
    }

    protected function promotions()
    {
        if (! Schema::hasTable('promotions')) {
            return collect();
        }

        return Promotion::query()
            ->homepageVisible()
            ->ordered()
            ->get();
    }

    protected function reviews(?string $gameSlug = null)
    {
        if (! Schema::hasTable('testimonials')) {
            return collect();
        }

        $query = Review::query()->orderBy('sort_order')->latest('id')->limit(6);

        $this->scopeToGame($query, $gameSlug);

        return $query->get(['author_name', 'game_id', 'service_id', 'service', 'quote', 'sort_order']);
    }

    protected function latestBlogArticles(?string $gameSlug = null)
    {
        if (! Schema::hasTable('blog_articles')) {
            return collect();
        }

        $query = BlogArticle::query()
            ->latestPublished()
            ->limit(6);

        $this->scopeToGame($query, $gameSlug);

        return $query->get();
    }

    protected function scopeToGame($query, ?string $gameSlug): void
    {
        if (! $gameSlug || ! Schema::hasTable('games') || ! Schema::hasColumn($query->getModel()->getTable(), 'game_id')) {
            return;
        }

        $gameId = $this->games->findBySlug($gameSlug)?->id;

        if (! $gameId) {
            return;
        }

        $query->where(function ($builder) use ($gameId): void {
            $builder->whereNull('game_id')->orWhere('game_id', $gameId);
        });
    }
}
