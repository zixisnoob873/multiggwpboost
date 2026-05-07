<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\StoreReviewRequest;
use App\Http\Requests\Admin\UpdateReviewRequest;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminReviewController extends AdminController
{
    public function index(): View
    {
        return $this->renderPage('admin.reviews.index', [
            'reviews' => Review::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return $this->renderPage('admin.reviews.create');
    }

    public function store(StoreReviewRequest $request): RedirectResponse
    {
        $review = Review::query()->create($request->validated());
        $this->audit('marketing', 'review_created', $review, [], $request);

        return redirect()
            ->route('admin-reviews.index')
            ->with('status', 'Review created successfully.');
    }

    public function edit(Review $review): View
    {
        return $this->renderPage('admin.reviews.edit', [
            'review' => $review,
        ]);
    }

    public function update(UpdateReviewRequest $request, Review $review): RedirectResponse
    {
        $review->update($request->validated());
        $this->audit('marketing', 'review_updated', $review, [], $request);

        return redirect()
            ->route('admin-reviews.edit', ['review' => $review])
            ->with('status', 'Review updated successfully.');
    }

    public function destroy(Request $request, Review $review): RedirectResponse
    {
        $label = $review->author_name ?? $review->title ?? 'Review';
        $review->delete();
        $this->audit('marketing', 'review_deleted', $label, [], $request);

        return redirect()
            ->route('admin-reviews.index')
            ->with('status', 'Review deleted successfully.');
    }
}
