<?php

namespace App\Queries\Admin;

use App\Models\AddonSetting;
use App\Models\Faq;
use App\Models\FeaturedBooster;
use App\Models\Page;
use App\Support\BoostingCatalog;

class ContentHomeQuery
{
    public function execute(): array
    {
        $pages = Page::query()->latest('updated_at')->get();
        $faqs = Faq::query()->orderBy('order')->get();
        $featuredBoosters = FeaturedBooster::query()->orderBy('sort_order')->latest('updated_at')->get();
        $addonSettings = AddonSetting::query()->orderBy('sort_order')->get();
        $expectedAddons = collect(BoostingCatalog::addonDefinitions());
        $recentChanges = collect()
            ->merge($pages->take(5)->map(fn ($page) => [
                'label' => 'Page',
                'title' => (string) ($page->title ?? $page->key ?? 'Page'),
                'updated_at' => $page->updated_at,
            ]))
            ->merge($faqs->take(5)->map(fn ($faq) => [
                'label' => 'FAQ',
                'title' => $faq->question,
                'updated_at' => $faq->updated_at,
            ]))
            ->merge($featuredBoosters->take(5)->map(fn ($booster) => [
                'label' => 'Featured booster',
                'title' => $booster->name,
                'updated_at' => $booster->updated_at,
            ]))
            ->sortByDesc(fn (array $item) => optional($item['updated_at'])->timestamp ?? 0)
            ->take(8)
            ->values();

        $publishingWarnings = collect();

        if ($faqs->isEmpty()) {
            $publishingWarnings->push('No FAQs are currently published.');
        }

        if ($featuredBoosters->isEmpty()) {
            $publishingWarnings->push('No featured boosters are set for the homepage.');
        }

        if ($addonSettings->count() < $expectedAddons->count()) {
            $publishingWarnings->push('Some addon tooltips still rely on default copy.');
        }

        return [
            'contentCounts' => [
                'pages' => $pages->count(),
                'faqs' => $faqs->count(),
                'featured_boosters' => $featuredBoosters->count(),
                'addon_tooltips' => $expectedAddons->count(),
            ],
            'recentChanges' => $recentChanges,
            'publishingWarnings' => $publishingWarnings,
            'faqs' => $faqs,
            'featuredBoosters' => $featuredBoosters,
            'addonSettings' => $expectedAddons->map(function (array $addon) use ($addonSettings): array {
                $record = $addonSettings->firstWhere('slug', $addon['slug']);

                return [
                    'slug' => $addon['slug'],
                    'label' => $addon['label'],
                    'description' => $record?->description ?? $addon['description'],
                    'sort_order' => $addon['sort_order'],
                ];
            }),
        ];
    }
}
