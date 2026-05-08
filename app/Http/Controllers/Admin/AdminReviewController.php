<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\StoreReviewRequest;
use App\Http\Requests\Admin\UpdateReviewRequest;
use App\Models\Game;
use App\Models\GameService;
use App\Models\Review;
use App\Support\MarketplaceCatalogCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminReviewController extends AdminController
{
    public function __construct(
        protected MarketplaceCatalogCache $catalogCache,
    ) {}

    public function index(): View
    {
        return $this->renderPage('admin.reviews.index', [
            'reviews' => Review::query()
                ->with(['game', 'gameService'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return $this->renderPage('admin.reviews.create', $this->formPayload());
    }

    public function store(StoreReviewRequest $request): RedirectResponse
    {
        $review = Review::query()->create($request->validated());
        $this->catalogCache->clear();
        $this->audit('marketing', 'review_created', $review, [], $request);

        return redirect()
            ->route('admin-reviews.index')
            ->with('status', 'Review created successfully.');
    }

    public function edit(Review $review): View
    {
        return $this->renderPage('admin.reviews.edit', [
            'review' => $review,
        ] + $this->formPayload());
    }

    public function update(UpdateReviewRequest $request, Review $review): RedirectResponse
    {
        $review->update($request->validated());
        $this->catalogCache->clear();
        $this->audit('marketing', 'review_updated', $review, [], $request);

        return redirect()
            ->route('admin-reviews.edit', ['review' => $review])
            ->with('status', 'Review updated successfully.');
    }

    public function destroy(Request $request, Review $review): RedirectResponse
    {
        $label = $review->author_name ?? $review->title ?? 'Review';
        $review->delete();
        $this->catalogCache->clear();
        $this->audit('marketing', 'review_deleted', $label, [], $request);

        return redirect()
            ->route('admin-reviews.index')
            ->with('status', 'Review deleted successfully.');
    }

    protected function formPayload(): array
    {
        return [
            'games' => Game::query()->orderBy('sort_order')->orderBy('name')->get(),
            'services' => GameService::query()->with('game')->orderBy('game_id')->orderBy('sort_order')->orderBy('name')->get(),
        ];
    }
}
