<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\AdminBlogArticleIndexRequest;
use App\Http\Requests\Admin\StoreBlogArticleRequest;
use App\Http\Requests\Admin\UpdateBlogArticleRequest;
use App\Models\BlogArticle;
use App\Queries\Admin\BlogArticleIndexQuery;
use App\Support\Cms\BlogArticleContentSerializer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class AdminBlogArticleController extends AdminController
{
    public function __construct(
        private readonly BlogArticleIndexQuery $blogArticleIndexQuery,
        private readonly BlogArticleContentSerializer $blogArticleContentSerializer,
    ) {}

    public function index(AdminBlogArticleIndexRequest $request): View
    {
        return $this->renderPage('admin.blog-articles.index', $this->blogArticleIndexQuery->execute($request));
    }

    public function create(): View
    {
        return $this->renderPage('admin.blog-articles.create', [
            'blogArticle' => new BlogArticle([
                'status' => BlogArticle::STATUS_DRAFT,
                'include_in_sitemap' => true,
            ]),
            'bodySections' => [$this->blogArticleContentSerializer->blankSection()],
        ]);
    }

    public function store(StoreBlogArticleRequest $request): RedirectResponse
    {
        $article = BlogArticle::query()->create($this->payload($request->validated()));
        $this->audit('marketing', 'blog_article_created', $article, [
            'status' => $article->status,
        ], $request);

        return redirect()
            ->route('admin-blog-articles.edit', $article)
            ->with('status', 'Blog article created successfully.');
    }

    public function edit(BlogArticle $blogArticle): View
    {
        return $this->renderPage('admin.blog-articles.edit', [
            'blogArticle' => $blogArticle,
            'bodySections' => $this->blogArticleContentSerializer->deserialize((string) $blogArticle->body),
        ]);
    }

    public function update(UpdateBlogArticleRequest $request, BlogArticle $blogArticle): RedirectResponse
    {
        $beforeStatus = $blogArticle->status;
        $blogArticle->update($this->payload($request->validated(), $blogArticle));
        $this->audit('marketing', 'blog_article_updated', $blogArticle, [
            'before_status' => $beforeStatus,
            'after_status' => $blogArticle->status,
        ], $request);

        return redirect()
            ->route('admin-blog-articles.edit', $blogArticle)
            ->with('status', 'Blog article updated successfully.');
    }

    public function publish(Request $request, BlogArticle $blogArticle): RedirectResponse
    {
        if ($blogArticle->status === BlogArticle::STATUS_PUBLISHED) {
            return redirect()
                ->route('admin-blog-articles.edit', $blogArticle)
                ->with('status', 'Blog article is already published.');
        }

        $blogArticle->update([
            'status' => BlogArticle::STATUS_PUBLISHED,
            'published_at' => $blogArticle->published_at ?? now(),
        ]);
        $this->audit('marketing', 'blog_article_published', $blogArticle, [], $request);

        return redirect()
            ->route('admin-blog-articles.edit', $blogArticle)
            ->with('status', 'Blog article published.');
    }

    public function unpublish(Request $request, BlogArticle $blogArticle): RedirectResponse
    {
        if ($blogArticle->status !== BlogArticle::STATUS_PUBLISHED) {
            return redirect()
                ->route('admin-blog-articles.edit', $blogArticle)
                ->with('status', 'Blog article is already in draft.');
        }

        $blogArticle->update([
            'status' => BlogArticle::STATUS_DRAFT,
        ]);
        $this->audit('marketing', 'blog_article_unpublished', $blogArticle, [], $request);

        return redirect()
            ->route('admin-blog-articles.edit', $blogArticle)
            ->with('status', 'Blog article moved to draft.');
    }

    protected function payload(array $validated, ?BlogArticle $blogArticle = null): array
    {
        $status = $validated['status'];
        $publishedAt = Arr::get($validated, 'published_at');

        if ($status === BlogArticle::STATUS_PUBLISHED && blank($publishedAt)) {
            $publishedAt = $blogArticle?->published_at ?? now();
        }

        return [
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'excerpt' => $validated['excerpt'],
            'intro' => $validated['intro'],
            'body' => $this->blogArticleContentSerializer->serialize(Arr::get($validated, 'body_sections', [])),
            'faq_items' => Arr::get($validated, 'faq_items', []),
            'cta_label' => Arr::get($validated, 'cta_label'),
            'cta_url' => Arr::get($validated, 'cta_url'),
            'meta_title' => Arr::get($validated, 'meta_title'),
            'meta_description' => Arr::get($validated, 'meta_description'),
            'canonical_url' => Arr::get($validated, 'canonical_url'),
            'robots' => Arr::get($validated, 'robots'),
            'status' => $status,
            'published_at' => $publishedAt,
            'include_in_sitemap' => (bool) Arr::get($validated, 'include_in_sitemap', false),
        ];
    }
}
