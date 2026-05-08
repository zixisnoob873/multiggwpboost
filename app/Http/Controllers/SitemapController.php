<?php

namespace App\Http\Controllers;

use App\Models\BlogArticle;
use App\Models\Game;
use App\Models\GameCategory;
use App\Models\GameService;
use App\Queries\Marketplace\GameRepository;
use App\Queries\Marketplace\ServiceCategoryCatalog;
use App\Queries\Marketplace\ServiceRepository;
use App\Support\Cms\PageContentService;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SitemapController extends Controller
{
    public function __construct(
        protected PageContentService $pageContentService,
        protected GameRepository $games,
        protected ServiceRepository $services,
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

        $activeGames = $this->games->activeGames();

        $serviceCategoryPages = Collection::make(ServiceCategoryCatalog::all())
            ->map(function (array $category): ?array {
                $services = $this->services->servicesForCategory($category);

                if ($services->isEmpty()) {
                    return null;
                }

                return [
                    'loc' => route('services.categories.show', ['category' => $category['slug']]),
                    'lastmod' => $services->max('updated_at'),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ];
            })
            ->filter()
            ->values();

        $categoryPages = $this->games->activeCategories()
            ->filter(fn (GameCategory $category): bool => $this->seoMetadataIsIndexable($category->seoMetadata))
            ->map(fn (GameCategory $category): array => [
                'loc' => route('games.categories.show', ['category' => $category->slug]),
                'lastmod' => $category->updated_at,
                'changefreq' => $category->seoMetadata?->changefreq ?: 'weekly',
                'priority' => $category->seoMetadata?->priority !== null ? number_format((float) $category->seoMetadata->priority, 1) : '0.7',
            ]);

        $gameModels = $activeGames
            ->filter(fn (Game $game): bool => $this->seoMetadataIsIndexable($game->seoMetadata))
            ->values();

        $gamePages = $gameModels
            ->map(fn (Game $game): array => [
                'loc' => route('game.show', ['game' => $game->slug]),
                'lastmod' => $game->updated_at,
                'changefreq' => $game->seoMetadata?->changefreq ?: 'weekly',
                'priority' => $game->seoMetadata?->priority !== null ? number_format((float) $game->seoMetadata->priority, 1) : '0.8',
            ]);

        $servicePages = $activeGames
            ->flatMap(fn (Game $game): Collection => $this->services->servicesByGameSlug($game->slug))
            ->filter(fn (GameService $service): bool => $this->seoMetadataIsIndexable($service->seoMetadata))
            ->map(fn (GameService $service): array => [
                'loc' => route('game.services.show', [
                    'game' => $service->game->slug,
                    'service' => $service->slug,
                ]),
                'lastmod' => $service->updated_at,
                'changefreq' => $service->seoMetadata?->changefreq ?: 'weekly',
                'priority' => $service->seoMetadata?->priority !== null ? number_format((float) $service->seoMetadata->priority, 1) : '0.7',
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
                    ->merge($serviceCategoryPages)
                    ->merge($categoryPages)
                    ->merge($gamePages)
                    ->merge($servicePages)
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

    protected function seoMetadataIsIndexable(mixed $seoMetadata): bool
    {
        if (! $seoMetadata) {
            return true;
        }

        return (bool) ($seoMetadata->include_in_sitemap ?? true)
            && ! str_contains(strtolower((string) $seoMetadata->robots), 'noindex');
    }
}
