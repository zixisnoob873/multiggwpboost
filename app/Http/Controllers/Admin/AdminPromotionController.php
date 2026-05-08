<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\AdminPromotionIndexRequest;
use App\Http\Requests\Admin\StorePromotionRequest;
use App\Http\Requests\Admin\UpdatePromotionRequest;
use App\Models\Promotion;
use App\Queries\Admin\PromotionIndexQuery;
use App\Queries\HomePageContentQuery;
use App\Services\Security\PromotionImageStorageService;
use App\Support\MarketplaceCatalogCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class AdminPromotionController extends AdminController
{
    public function __construct(
        private readonly PromotionIndexQuery $promotionIndexQuery,
        private readonly PromotionImageStorageService $promotionImageStorageService,
        private readonly MarketplaceCatalogCache $marketplaceCatalogCache,
    ) {}

    public function index(AdminPromotionIndexRequest $request): View
    {
        return $this->renderPage('admin.promotions.index', $this->promotionIndexQuery->execute($request));
    }

    public function edit(Promotion $promotion): View
    {
        return $this->renderPage('admin.promotions.edit', [
            'promotion' => $promotion,
        ]);
    }

    public function store(StorePromotionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $imagePath = $this->promotionImageStorageService->store($validated['image']);

        try {
            $promotion = Promotion::query()->create($this->payload(
                $validated,
                $request->boolean('is_active', false),
                $request->boolean('show_on_homepage', false),
                $imagePath,
            ));
        } catch (\Throwable $exception) {
            $this->promotionImageStorageService->deleteIfUnused($imagePath);

            throw $exception;
        }

        $this->clearCachedPublicContent();
        $this->audit('marketing', 'promotion_created', $promotion, [
            'is_active' => $promotion->is_active,
            'show_on_homepage' => $promotion->show_on_homepage,
        ], $request);

        return redirect()
            ->route('admin-promotions.index')
            ->with('status', "Promotion {$promotion->title} created successfully.");
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion): RedirectResponse
    {
        $validated = $request->validated();
        $imagePath = $promotion->image_path;
        $oldImagePath = $promotion->image_path;
        $before = [
            'is_active' => $promotion->is_active,
            'show_on_homepage' => $promotion->show_on_homepage,
            'sort_order' => $promotion->sort_order,
        ];

        if (isset($validated['image'])) {
            $imagePath = $this->promotionImageStorageService->store($validated['image']);
        }

        try {
            $promotion->update($this->payload(
                $validated,
                $request->boolean('is_active', false),
                $request->boolean('show_on_homepage', false),
                $imagePath,
            ));
        } catch (\Throwable $exception) {
            if ($imagePath !== $oldImagePath) {
                $this->promotionImageStorageService->deleteIfUnused($imagePath, $promotion->getKey());
            }

            throw $exception;
        }

        if ($imagePath !== $oldImagePath) {
            $this->promotionImageStorageService->deleteIfUnused($oldImagePath, $promotion->getKey());
        }

        $this->clearCachedPublicContent();
        $this->audit('marketing', 'promotion_updated', $promotion, [
            'before' => $before,
            'after' => [
                'is_active' => $promotion->is_active,
                'show_on_homepage' => $promotion->show_on_homepage,
                'sort_order' => $promotion->sort_order,
            ],
        ], $request);

        return redirect()
            ->route('admin-promotions.edit', $promotion)
            ->with('status', "Promotion {$promotion->title} updated.");
    }

    public function destroy(\Illuminate\Http\Request $request, Promotion $promotion): RedirectResponse
    {
        $imagePath = $promotion->image_path;
        $title = $promotion->title;

        $promotion->delete();
        $this->promotionImageStorageService->deleteIfUnused($imagePath);
        $this->clearCachedPublicContent();
        $this->audit('marketing', 'promotion_deleted', $title, [], $request);

        return redirect()
            ->route('admin-promotions.index')
            ->with('status', "Promotion {$title} deleted.");
    }

    public function toggleActive(Request $request, Promotion $promotion): RedirectResponse
    {
        $promotion->update([
            'is_active' => ! $promotion->is_active,
        ]);

        $this->clearCachedPublicContent();

        $status = $promotion->fresh()?->is_active ? 'activated' : 'deactivated';
        $this->audit('marketing', 'promotion_visibility_updated', $promotion, [
            'is_active' => $promotion->fresh()?->is_active,
        ], $request);

        return redirect()
            ->route('admin-promotions.edit', $promotion)
            ->with('status', "Promotion {$promotion->title} {$status}.");
    }

    public function toggleHomepage(\Illuminate\Http\Request $request, Promotion $promotion): RedirectResponse
    {
        $promotion->update([
            'show_on_homepage' => ! $promotion->show_on_homepage,
        ]);

        $this->clearCachedPublicContent();

        $status = $promotion->fresh()?->show_on_homepage ? 'is now visible on the homepage' : 'was removed from the homepage';
        $this->audit('marketing', 'promotion_homepage_updated', $promotion, [
            'show_on_homepage' => $promotion->fresh()?->show_on_homepage,
        ], $request);

        return redirect()
            ->route('admin-promotions.edit', $promotion)
            ->with('status', "Promotion {$promotion->title} {$status}.");
    }

    protected function payload(array $validated, bool $isActive, bool $showOnHomepage, string $imagePath): array
    {
        return [
            'title' => $validated['title'],
            'description' => $validated['description'],
            'image_path' => $imagePath,
            'button_text' => $validated['button_text'] ?? null,
            'button_link' => $validated['button_link'] ?? null,
            'is_active' => $isActive,
            'show_on_homepage' => $showOnHomepage,
            'sort_order' => (int) $validated['sort_order'],
        ];
    }

    protected function clearCachedPublicContent(): void
    {
        Cache::forget(HomePageContentQuery::CACHE_KEY);
        $this->marketplaceCatalogCache->clear();
    }
}
