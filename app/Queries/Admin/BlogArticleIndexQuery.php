<?php

namespace App\Queries\Admin;

use App\Models\BlogArticle;
use Illuminate\Http\Request;

class BlogArticleIndexQuery
{
    public function execute(Request $request): array
    {
        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', ''));
        $perPage = max(10, min(100, (int) $request->input('per_page', 20)));

        $query = BlogArticle::query()
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($nested) use ($search): void {
                    $nested
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, [BlogArticle::STATUS_DRAFT, BlogArticle::STATUS_PUBLISHED], true), function ($builder) use ($status): void {
                $builder->where('status', $status);
            })
            ->orderByRaw("case when status = 'published' then 0 else 1 end")
            ->orderByDesc('published_at')
            ->orderByDesc('updated_at');

        return [
            'blogArticles' => $query->paginate($perPage)->withQueryString(),
            'blogArticleSearch' => $search,
            'blogArticleStatus' => $status,
            'blogArticleStats' => [
                'total' => BlogArticle::query()->count(),
                'published' => BlogArticle::query()->where('status', BlogArticle::STATUS_PUBLISHED)->count(),
                'drafts' => BlogArticle::query()->where('status', BlogArticle::STATUS_DRAFT)->count(),
            ],
        ];
    }
}
