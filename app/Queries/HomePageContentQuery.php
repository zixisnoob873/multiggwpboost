<?php

namespace App\Queries;

use App\Models\BlogArticle;
use App\Models\Faq;
use App\Models\FeaturedBooster;
use App\Models\Promotion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class HomePageContentQuery
{
    public const CACHE_KEY = 'home-page-content.v5';

    public function execute(): array
    {
        $content = Cache::remember(self::CACHE_KEY, now()->addMinutes(5), fn (): array => [
            'faqs' => $this->faqs(),
            'featuredBoosters' => $this->featuredBoosters(),
            'promotions' => $this->promotions(),
        ]);

        $content['latestBlogArticles'] = $this->latestBlogArticles();

        return $content;
    }

    protected function faqs()
    {
        if (! Schema::hasTable('faqs')) {
            return collect();
        }

        return Faq::orderBy('order')->get(['question', 'answer']);
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

    protected function latestBlogArticles()
    {
        if (! Schema::hasTable('blog_articles')) {
            return collect();
        }

        return BlogArticle::query()
            ->latestPublished()
            ->limit(6)
            ->get();
    }
}
