<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\User;
use App\Support\Cms\PageContentService;
use App\Support\Seo\StructuredDataBuilder;
use App\Services\Payments\PaymentManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CheckoutPageController extends Controller
{
    public function __construct(
        protected PaymentManager $paymentManager,
        protected PageContentService $pageContentService,
        protected StructuredDataBuilder $structuredData,
    ) {}

    public function show(): View
    {
        $providers = $this->paymentManager->allDescriptors();
        $defaultProvider = $this->paymentManager->defaultProvider();
        $viewer = Auth::user();
        $checkoutBlockedForBooster = User::normalizeRole($viewer?->role) === User::ROLE_BOOSTER;

        $seo = [
            'title' => 'VALORANT Boost Pricing | Cheap & Fast Rank Boosting For VALORANT',
            'description' => 'Review your VALORANT boost price, confirm service details, choose payment, and start rank boosting fast.',
            'canonical' => route('checkout'),
            'robots' => 'index,follow',
            'type' => 'website',
        ];
        $seo['schema'] = $this->structuredData->checkout($seo);

        return view('checkout', [
            'paymentProviders' => $providers,
            'defaultPaymentProvider' => $defaultProvider->toArray(),
            'checkoutBlockedForBooster' => $checkoutBlockedForBooster,
            'seo' => $seo,
        ]);
    }

    public function codeOfEthics(): View
    {
        return $this->renderCmsPage('code-of-ethics', 'code-of-ethics');
    }

    public function privacyPolicy(): View
    {
        return $this->renderCmsPage('privacy-policy', 'privacy-policy');
    }

    public function refundPolicy(): View
    {
        return $this->renderCmsPage('refund-policy', 'refund-policy');
    }

    public function reviews(): View
    {
        $pageContent = $this->pageContentService->publicContent('reviews');
        $reviews = Review::query()->orderBy('sort_order')->latest('id')->get();
        $seo = $this->pageContentService->seo('reviews');
        $seo['schema'] = $this->structuredData->reviews(
            $pageContent,
            $seo,
            $reviews,
            $this->pageContentService->page('reviews')?->updated_at
        );

        return view('reviews', [
            'pageContent' => $pageContent,
            'seo' => $seo,
            'reviews' => $reviews,
        ]);
    }

    public function termsAndConditions(): View
    {
        return $this->renderCmsPage('terms-and-conditions', 'terms-and-conditions');
    }

    protected function renderCmsPage(string $pageKey, string $view): View
    {
        $pageContent = $this->pageContentService->publicContent($pageKey);
        $seo = $this->pageContentService->seo($pageKey);
        $seo['schema'] = $this->structuredData->legalPage(
            $pageKey,
            $pageContent,
            $seo,
            $this->pageContentService->page($pageKey)?->updated_at
        );

        return view($view, [
            'pageContent' => $pageContent,
            'seo' => $seo,
        ]);
    }
}
