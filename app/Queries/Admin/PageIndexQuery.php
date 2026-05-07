<?php

namespace App\Queries\Admin;

use App\Support\Cms\PageContentService;

class PageIndexQuery
{
    public function __construct(
        protected PageContentService $pageContentService,
    ) {}

    public function execute(): array
    {
        $pages = collect($this->pageContentService->definitions())
            ->map(function (array $definition): array {
                $page = $this->pageContentService->page($definition['key']);

                return [
                    'key' => $definition['key'],
                    'label' => $definition['label'],
                    'path' => $this->pageContentService->pagePath($definition['key']),
                    'include_in_sitemap' => $this->pageContentService->includeInSitemap($definition['key']),
                    'updated_at' => $page?->updated_at,
                ];
            })
            ->values();

        return [
            'pages' => $pages,
        ];
    }
}
