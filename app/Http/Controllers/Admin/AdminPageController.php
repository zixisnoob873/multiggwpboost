<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\UpdatePageRequest;
use App\Queries\Admin\PageIndexQuery;
use App\Support\Cms\PageContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminPageController extends AdminController
{
    public function __construct(
        protected PageIndexQuery $pageIndexQuery,
        protected PageContentService $pageContentService,
    ) {}

    public function index(): View
    {
        return $this->renderPage('admin.pages.index', $this->pageIndexQuery->execute());
    }

    public function edit(string $pageKey): View
    {
        $definition = $this->pageContentService->definition($pageKey);

        return $this->renderPage('admin.pages.edit', [
            'pageDefinition' => $definition,
            'pageRecord' => $this->pageContentService->page($pageKey),
            'pageContent' => $this->pageContentService->editableContent($pageKey),
            'pagePath' => $this->pageContentService->pagePath($pageKey),
        ]);
    }

    public function update(UpdatePageRequest $request, string $pageKey): RedirectResponse
    {
        $page = $this->pageContentService->save($pageKey, $request->validated());
        $this->audit('content', 'page_updated', $page, [
            'page_key' => $pageKey,
            'include_in_sitemap' => $page->include_in_sitemap,
        ], $request);

        return redirect()
            ->route('admin-pages.edit', ['pageKey' => $pageKey])
            ->with('status', 'Page content updated successfully.');
    }
}
