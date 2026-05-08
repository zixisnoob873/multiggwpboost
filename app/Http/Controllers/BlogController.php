<?php

namespace App\Http\Controllers;

use App\Models\BlogArticle;
use App\Support\Cms\PageContentService;
use App\Support\Seo\MarketplaceSeo;
use App\Support\Seo\StructuredDataBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function __construct(
        protected PageContentService $pageContentService,
        protected StructuredDataBuilder $structuredData,
        protected MarketplaceSeo $marketplaceSeo,
    ) {}

    public function index(): View
    {
        return $this->indexView();
    }

    public function category(string $category): View
    {
        return $this->indexView('category', Str::slug($category));
    }

    public function tag(string $tag): View
    {
        return $this->indexView('tag', Str::slug($tag));
    }

    protected function indexView(?string $archiveType = null, ?string $archiveSlug = null): View
    {
        $articles = Schema::hasTable('blog_articles')
            ? BlogArticle::query()
                ->latestPublished()
                ->when($archiveType === 'category' && $archiveSlug, fn ($query) => $query->inCategory($archiveSlug))
                ->when($archiveType === 'tag' && $archiveSlug, fn ($query) => $query->tagged($archiveSlug))
                ->paginate(9)
                ->withQueryString()
            : $this->emptyPaginator($archiveType, $archiveSlug);

        $pageContent = $this->pageContentService->publicContent('blog-index');
        $seo = $this->pageContentService->seo('blog-index');
        $archive = $this->archivePayload($archiveType, $archiveSlug, $articles->items());

        if ($archive !== null) {
            $pageContent = $this->archivePageContent($pageContent, $archive);
            $seo = array_merge($seo, [
                'title' => $archive['title'],
                'description' => $archive['description'],
                'canonical' => $archive['url'],
            ]);
        }

        $canonical = $articles->currentPage() > 1
            ? $this->archiveRoute($archiveType, $archiveSlug, ['page' => $articles->currentPage()])
            : ($seo['canonical'] ?: $this->archiveRoute($archiveType, $archiveSlug));

        $seo = array_merge($seo, [
            'canonical' => $canonical,
        ]);
        $seo['schema'] = $this->structuredData->blogIndex($pageContent, $seo, $articles);

        return view('blog.index', [
            'articles' => $articles,
            'pageContent' => $pageContent,
            'seo' => $seo,
            'archive' => $archive,
        ]);
    }

    public function show(string $slug): View
    {
        abort_unless(Schema::hasTable('blog_articles'), 404);

        $article = BlogArticle::query()
            ->with(['game', 'gameService.game', 'seoMetadata'])
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        $relatedArticles = BlogArticle::query()
            ->with(['game', 'gameService.game'])
            ->latestPublished()
            ->whereKeyNot($article->getKey())
            ->when(
                $article->service_id || $article->game_id || $article->category_slug || $article->tags() !== [],
                function ($query) use ($article): void {
                    $query
                        ->where(function ($builder) use ($article): void {
                            if ($article->service_id) {
                                $builder->orWhere('service_id', $article->service_id);
                            }

                            if ($article->game_id) {
                                $builder->orWhere('game_id', $article->game_id);
                            }

                            if ($article->category_slug) {
                                $builder->orWhere('category_slug', $article->category_slug);
                            }

                            foreach ($article->tags() as $tag) {
                                $builder->orWhereJsonContains('tags', $tag);
                            }
                        });
                }
            )
            ->limit(3)
            ->get();

        $seo = array_merge($this->marketplaceSeo->article($article), [
            'published_time' => $article->published_at?->toIso8601String(),
            'modified_time' => $article->updated_at?->toIso8601String(),
        ]);
        $seo['schema'] = $this->structuredData->blogArticle($article, $relatedArticles);

        return view('blog.show', [
            'article' => $article,
            'relatedArticles' => $relatedArticles,
            'seo' => $seo,
            'breadcrumbs' => [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Blog', 'url' => route('blog.index')],
                ['name' => $article->title, 'url' => $seo['canonical']],
            ],
        ]);
    }

    protected function emptyPaginator(?string $archiveType, ?string $archiveSlug): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 9, request()->integer('page', 1), [
            'path' => $this->archiveRoute($archiveType, $archiveSlug),
            'pageName' => 'page',
        ]);
    }

    protected function archivePayload(?string $archiveType, ?string $archiveSlug, array $articles): ?array
    {
        if (! in_array($archiveType, ['category', 'tag'], true) || ! $archiveSlug) {
            return null;
        }

        $label = $archiveType === 'category'
            ? collect($articles)->first()?->categoryLabel()
            : BlogArticle::tagLabel($archiveSlug);
        $label = $label ?: BlogArticle::tagLabel($archiveSlug);
        $typeLabel = $archiveType === 'category' ? 'Category' : 'Tag';
        $url = $this->archiveRoute($archiveType, $archiveSlug);

        return [
            'type' => $archiveType,
            'type_label' => $typeLabel,
            'slug' => $archiveSlug,
            'label' => $label,
            'url' => $url,
            'title' => "{$label} Blog Guides | GGWPBoost",
            'description' => Str::limit("Read GGWP-Boost {$label} guides with practical service context, ranking explanations, and internal links to relevant boosts.", 130, ''),
        ];
    }

    protected function archivePageContent(array $pageContent, array $archive): array
    {
        data_set($pageContent, 'hero.eyebrow', 'BLOG '.$archive['type_label']);
        data_set($pageContent, 'hero.headline', $archive['label'].' Guides');
        data_set($pageContent, 'hero.description', 'Browse published GGWP-Boost articles for '.$archive['label'].' with clean explanations and links to relevant game services.');
        data_set($pageContent, 'listing.title', $archive['label'].' Articles');
        data_set($pageContent, 'listing.description', 'Filtered guides from the GGWP-Boost blog archive.');

        return $pageContent;
    }

    protected function archiveRoute(?string $archiveType, ?string $archiveSlug, array $parameters = []): string
    {
        return match ($archiveType) {
            'category' => route('blog.category', ['category' => $archiveSlug] + $parameters),
            'tag' => route('blog.tag', ['tag' => $archiveSlug] + $parameters),
            default => route('blog.index', $parameters),
        };
    }
}
