<?php

namespace App\Http\Controllers;

use App\Models\BlogArticle;
use App\Support\Cms\PageContentService;
use App\Support\Seo\StructuredDataBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function __construct(
        protected PageContentService $pageContentService,
        protected StructuredDataBuilder $structuredData,
    ) {}

    public function index(): View
    {
        $articles = Schema::hasTable('blog_articles')
            ? BlogArticle::query()->latestPublished()->paginate(9)->withQueryString()
            : new LengthAwarePaginator([], 0, 9, request()->integer('page', 1), [
                'path' => route('blog.index'),
                'pageName' => 'page',
            ]);

        $pageContent = $this->pageContentService->publicContent('blog-index');
        $seo = $this->pageContentService->seo('blog-index');
        $canonical = $articles->currentPage() > 1
            ? route('blog.index', ['page' => $articles->currentPage()])
            : ($seo['canonical'] ?: route('blog.index'));

        $seo = array_merge($seo, [
            'canonical' => $canonical,
        ]);
        $seo['schema'] = $this->structuredData->blogIndex($pageContent, $seo, $articles);

        return view('blog.index', [
            'articles' => $articles,
            'pageContent' => $pageContent,
            'seo' => $seo,
        ]);
    }

    public function show(string $slug): View
    {
        abort_unless(Schema::hasTable('blog_articles'), 404);

        $article = BlogArticle::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        $relatedArticles = BlogArticle::query()
            ->latestPublished()
            ->whereKeyNot($article->getKey())
            ->limit(3)
            ->get();

        $seo = [
            'title' => $article->effectiveMetaTitle(),
            'description' => $article->effectiveMetaDescription(),
            'canonical' => $article->effectiveCanonicalUrl(),
            'robots' => $article->effectiveRobots(),
            'type' => 'article',
            'published_time' => $article->published_at?->toIso8601String(),
            'modified_time' => $article->updated_at?->toIso8601String(),
        ];
        $seo['schema'] = $this->structuredData->blogArticle($article, $relatedArticles);

        return view('blog.show', [
            'article' => $article,
            'relatedArticles' => $relatedArticles,
            'seo' => $seo,
        ]);
    }
}
