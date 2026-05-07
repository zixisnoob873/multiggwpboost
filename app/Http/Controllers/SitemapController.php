<?php

namespace App\Http\Controllers;

use App\Models\BlogArticle;
use App\Support\Cms\PageContentService;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SitemapController extends Controller
{
    public function __construct(
        protected PageContentService $pageContentService,
    ) {}

    public function __invoke(): Response
    {
        $staticPages = $this->pageContentService->sitemapPages()
            ->map(function (array $page): array {
                $key = (string) $page['key'];

                if ($page['key'] === 'blog-index') {
                    $page['lastmod'] = Schema::hasTable('blog_articles')
                        ? BlogArticle::query()->visibleInSitemap()->max('updated_at') ?? $page['lastmod']
                        : $page['lastmod'];
                }

                unset($page['key']);

                return array_merge($page, $this->metadataForPageKey($key));
            });

        $publicUtilityPages = Collection::make([
            [
                'loc' => route('checkout'),
                'lastmod' => null,
                'changefreq' => 'weekly',
                'priority' => '0.9',
            ],
        ]);

        $articlePages = Schema::hasTable('blog_articles')
            ? BlogArticle::query()
                ->visibleInSitemap()
                ->get()
                ->map(fn (BlogArticle $article): array => [
                    'loc' => route('blog.show', ['slug' => $article->slug]),
                    'lastmod' => $article->updated_at ?? $article->published_at,
                    'changefreq' => 'monthly',
                    'priority' => '0.6',
                ])
            : Collection::make();

        return response()
            ->view('sitemap.xml', [
                'urls' => $staticPages
                    ->merge($publicUtilityPages)
                    ->merge($articlePages)
                    ->unique('loc')
                    ->values(),
            ])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    protected function metadataForPageKey(string $key): array
    {
        return match ($key) {
            'home' => ['changefreq' => 'daily', 'priority' => '1.0'],
            'blog-index' => ['changefreq' => 'weekly', 'priority' => '0.8'],
            'faq', 'reviews' => ['changefreq' => 'weekly', 'priority' => '0.7'],
            'contact' => ['changefreq' => 'monthly', 'priority' => '0.6'],
            'become-booster' => ['changefreq' => 'monthly', 'priority' => '0.5'],
            'code-of-ethics', 'privacy-policy', 'refund-policy', 'terms-and-conditions' => ['changefreq' => 'yearly', 'priority' => '0.3'],
            default => ['changefreq' => 'monthly', 'priority' => '0.5'],
        };
    }
}
