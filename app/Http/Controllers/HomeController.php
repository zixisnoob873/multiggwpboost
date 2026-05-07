<?php

namespace App\Http\Controllers;

use App\Queries\HomePageContentQuery;
use App\Support\Cms\PageContentService;
use App\Support\Seo\StructuredDataBuilder;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(
        protected HomePageContentQuery $homePageContentQuery,
        protected PageContentService $pageContentService,
        protected StructuredDataBuilder $structuredData,
    ) {}

    public function home(): View
    {
        $data = $this->homePageContentQuery->execute();
        $pageContent = $this->pageContentService->publicContent('home');
        $seo = $this->pageContentService->seo('home');
        $seo['schema'] = $this->structuredData->home(
            $pageContent,
            $seo,
            $data['faqs'] ?? [],
            $data['latestBlogArticles'] ?? [],
            $this->pageContentService->page('home')?->updated_at
        );

        return view('home', array_merge($data, [
            'pageContent' => $pageContent,
            'seo' => $seo,
        ]));
    }

    public function faq(): View
    {
        $data = $this->homePageContentQuery->execute();
        $pageContent = $this->pageContentService->publicContent('faq');
        $seo = $this->pageContentService->seo('faq');
        $seo['schema'] = $this->structuredData->faq(
            $pageContent,
            $seo,
            $data['faqs'] ?? [],
            $this->pageContentService->page('faq')?->updated_at
        );

        return view('faq', [
            'faqs' => $data['faqs'],
            'pageContent' => $pageContent,
            'seo' => $seo,
        ]);
    }
}
