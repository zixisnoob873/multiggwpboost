<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\StoreFaqAction;
use App\Actions\Admin\StoreFeaturedBoosterAction;
use App\Actions\Admin\UpdateFaqAction;
use App\Actions\Admin\UpdateFeaturedBoosterAction;
use App\Http\Requests\Admin\StoreFaqRequest;
use App\Http\Requests\Admin\StoreFeaturedBoosterRequest;
use App\Http\Requests\Admin\UpdateAddonTooltipRequest;
use App\Http\Requests\Admin\UpdateFaqRequest;
use App\Http\Requests\Admin\UpdateFeaturedBoosterRequest;
use App\Models\AddonSetting;
use App\Models\Faq;
use App\Models\FeaturedBooster;
use App\Models\Game;
use App\Models\GameService;
use App\Queries\Admin\ContentIndexQuery;
use App\Support\BoostingCatalog;
use App\Support\MarketplaceCatalogCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminContentController extends AdminController
{
    public function __construct(
        private readonly ContentIndexQuery $contentIndexQuery,
        private readonly StoreFaqAction $storeFaqAction,
        private readonly UpdateFaqAction $updateFaqAction,
        private readonly StoreFeaturedBoosterAction $storeFeaturedBoosterAction,
        private readonly UpdateFeaturedBoosterAction $updateFeaturedBoosterAction,
        private readonly MarketplaceCatalogCache $catalogCache,
    ) {}

    public function index(): View
    {
        return $this->renderPage('admin.content.index', $this->contentIndexQuery->execute());
    }

    public function faqs(): View
    {
        return $this->renderPage('admin.content.faqs', [
            'faqs' => Faq::query()->with(['game', 'gameService'])->orderBy('order')->paginate(20),
            'games' => Game::query()->orderBy('sort_order')->orderBy('name')->get(),
            'services' => GameService::query()->with('game')->orderBy('game_id')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function featuredBoosters(): View
    {
        return $this->renderPage('admin.content.featured-boosters', [
            'featuredBoosters' => \App\Models\FeaturedBooster::query()->orderBy('sort_order')->latest('id')->paginate(20),
        ]);
    }

    public function addonTooltips(): View
    {
        return $this->renderPage('admin.content.addon-tooltips', [
            'addonSettings' => collect(\App\Support\BoostingCatalog::addonSettingsForAdmin()),
        ]);
    }

    public function storeFaq(StoreFaqRequest $request): RedirectResponse
    {
        $faq = $this->storeFaqAction->execute($request->validated());
        $this->clearCachedPublicContent();
        $this->audit('content', 'faq_created', $faq, [
            'order' => $faq->order,
        ], $request);

        return redirect()->route('admin-content.faqs.index')->with('status', 'FAQ created.');
    }

    public function updateFaq(UpdateFaqRequest $request, Faq $faq): RedirectResponse
    {
        $previousOrder = $faq->order;
        $this->updateFaqAction->execute($faq, $request->validated());
        $this->clearCachedPublicContent();
        $this->audit('content', 'faq_updated', $faq, [
            'before_order' => $previousOrder,
            'after_order' => $faq->order,
        ], $request);

        return redirect()->route('admin-content.faqs.index')->with('status', 'FAQ updated.');
    }

    public function destroyFaq(\Illuminate\Http\Request $request, Faq $faq): RedirectResponse
    {
        $label = $faq->question;
        $faq->delete();
        $this->clearCachedPublicContent();
        $this->audit('content', 'faq_deleted', $label, [], $request);

        return redirect()->route('admin-content.faqs.index')->with('status', 'FAQ deleted.');
    }

    public function storeFeaturedBooster(StoreFeaturedBoosterRequest $request): RedirectResponse
    {
        $featuredBooster = $this->storeFeaturedBoosterAction->execute($request->validated());
        $this->clearCachedPublicContent();
        $this->audit('content', 'featured_booster_created', $featuredBooster, [
            'sort_order' => $featuredBooster->sort_order,
        ], $request);

        return redirect()->route('admin-content.featured-boosters.index')->with('status', 'Featured booster created.');
    }

    public function updateFeaturedBooster(UpdateFeaturedBoosterRequest $request, FeaturedBooster $featuredBooster): RedirectResponse
    {
        $previousSortOrder = $featuredBooster->sort_order;
        $this->updateFeaturedBoosterAction->execute($featuredBooster, $request->validated());
        $this->clearCachedPublicContent();
        $this->audit('content', 'featured_booster_updated', $featuredBooster, [
            'before_sort_order' => $previousSortOrder,
            'after_sort_order' => $featuredBooster->sort_order,
        ], $request);

        return redirect()->route('admin-content.featured-boosters.index')->with('status', 'Featured booster updated.');
    }

    public function destroyFeaturedBooster(\Illuminate\Http\Request $request, FeaturedBooster $featuredBooster): RedirectResponse
    {
        $label = $featuredBooster->name;
        $featuredBooster->delete();
        $this->clearCachedPublicContent();
        $this->audit('content', 'featured_booster_deleted', $label, [], $request);

        return redirect()->route('admin-content.featured-boosters.index')->with('status', 'Featured booster deleted.');
    }

    public function updateAddonTooltip(UpdateAddonTooltipRequest $request, string $addonSlug): RedirectResponse
    {
        BoostingCatalog::syncAddonSettings();
        $addon = collect(BoostingCatalog::addonDefinitions())
            ->firstWhere('slug', $addonSlug);

        abort_unless(is_array($addon), 404);

        AddonSetting::query()->updateOrCreate(
            ['slug' => $addonSlug],
            [
                'label' => $addon['label'],
                'description' => $request->validated('description'),
                'sort_order' => $addon['sort_order'],
            ]
        );

        $this->clearCachedPublicContent();
        $this->audit('content', 'addon_tooltip_updated', $addon['label'], [
            'slug' => $addonSlug,
        ], $request);

        return redirect()->route('admin-content.addon-tooltips.index')->with('status', 'Addon tooltip updated.');
    }

    protected function clearCachedPublicContent(): void
    {
        $this->catalogCache->clear();
    }
}
