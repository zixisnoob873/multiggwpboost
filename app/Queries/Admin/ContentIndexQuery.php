<?php

namespace App\Queries\Admin;

use App\Models\Faq;
use App\Models\FeaturedBooster;
use App\Support\Cms\PageContentService;
use App\Support\BoostingCatalog;
use Illuminate\Support\Collection;

class ContentIndexQuery
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

        $faqs = Faq::query()->orderBy('order')->get();
        $featuredBoosters = FeaturedBooster::query()->orderBy('sort_order')->latest('id')->get();
        $addonSettings = collect(BoostingCatalog::addonSettingsForAdmin());
        $recentChanges = $this->recentChanges($pages, $faqs, $featuredBoosters, $addonSettings);
        $warnings = $this->warnings($pages, $faqs, $featuredBoosters, $addonSettings);

        return [
            'pages' => $pages,
            'faqs' => $faqs,
            'featuredBoosters' => $featuredBoosters,
            'addonSettings' => $addonSettings,
            'contentCounts' => [
                'pages' => $pages->count(),
                'faqs' => $faqs->count(),
                'featured_boosters' => $featuredBoosters->count(),
                'addon_tooltips' => $addonSettings->count(),
            ],
            'recentChanges' => $recentChanges,
            'publishingWarnings' => $warnings,
        ];
    }

    protected function recentChanges(Collection $pages, Collection $faqs, Collection $featuredBoosters, Collection $addonSettings): Collection
    {
        return collect()
            ->merge($pages->map(fn (array $page): array => [
                'label' => $page['label'],
                'type' => 'Page',
                'route' => route('admin-pages.edit', ['pageKey' => $page['key']]),
                'updated_at' => $page['updated_at'],
            ]))
            ->merge($faqs->map(fn (Faq $faq): array => [
                'label' => $faq->question,
                'type' => 'FAQ',
                'route' => route('admin-content.faqs.index'),
                'updated_at' => $faq->updated_at,
            ]))
            ->merge($featuredBoosters->map(fn (FeaturedBooster $booster): array => [
                'label' => $booster->name,
                'type' => 'Featured Booster',
                'route' => route('admin-content.featured-boosters.index'),
                'updated_at' => $booster->updated_at,
            ]))
            ->merge($addonSettings->map(fn (array $addon): array => [
                'label' => $addon['label'],
                'type' => 'Addon Tooltip',
                'route' => route('admin-content.addon-tooltips.index'),
                'updated_at' => $addon['updated_at'] ?? null,
            ]))
            ->filter(fn (array $item): bool => $item['updated_at'] !== null)
            ->sortByDesc('updated_at')
            ->take(8)
            ->values();
    }

    protected function warnings(Collection $pages, Collection $faqs, Collection $featuredBoosters, Collection $addonSettings): Collection
    {
        $warnings = collect();

        if ($faqs->isEmpty()) {
            $warnings->push('FAQ coverage is empty, so support and SEO answers have no managed content.');
        }

        if ($featuredBoosters->isEmpty()) {
            $warnings->push('Featured boosters are empty, so homepage trust content has no live entries.');
        }

        if ($addonSettings->contains(fn (array $addon): bool => blank($addon['description'] ?? null))) {
            $warnings->push('One or more addon tooltips are blank and may leave upsell choices unexplained.');
        }

        if ($pages->contains(fn (array $page): bool => ($page['include_in_sitemap'] ?? false) === false)) {
            $warnings->push('At least one managed page is excluded from the sitemap. Confirm that this is intentional.');
        }

        return $warnings->take(4)->values();
    }
}
