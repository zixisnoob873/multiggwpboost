<?php

namespace App\Http\Controllers;

use App\Queries\HomePageContentQuery;
use App\Queries\Marketplace\MarketplacePageQuery;
use App\Support\Cms\PageContentService;
use App\Support\Seo\StructuredDataBuilder;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(
        protected MarketplacePageQuery $marketplacePageQuery,
        protected HomePageContentQuery $homePageContentQuery,
        protected PageContentService $pageContentService,
        protected StructuredDataBuilder $structuredData,
    ) {}

    public function home(): View
    {
        return view('home', $this->marketplacePageQuery->homePage());
    }

    public function game(string $game): View
    {
        $data = $this->marketplacePageQuery->gamePage($game);

        abort_unless($data !== null, 404);

        return view('home', $data);
    }

    public function service(string $game, string $service): View
    {
        $data = $this->marketplacePageQuery->servicePage($game, $service);

        abort_unless($data !== null, 404);

        return view('marketplace.service', $data);
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
