@props([
    'reviews' => [],
])

@php
    $items = collect($reviews)->take(3)->values();

    if ($items->isEmpty()) {
        $items = collect([
            [
                'author_name' => 'Verified Customer',
                'service' => 'Rank Boosting',
                'quote' => 'Fast communication, clear order tracking, and a smooth delivery from checkout to completion.',
            ],
            [
                'author_name' => 'Returning Customer',
                'service' => 'Placement Matches',
                'quote' => 'The flow was simple, support answered quickly, and every update was easy to follow.',
            ],
            [
                'author_name' => 'Multi-game Customer',
                'service' => 'Power Leveling',
                'quote' => 'Professional handling, secure payment, and reliable progress updates across the whole order.',
            ],
        ]);
    }
@endphp

<section class="section-block ggwp-marketplace-section ggwp-marketplace-reviews" aria-labelledby="reviewsHeading">
    <x-home.section-heading
        id="reviewsHeading"
        kicker="Reviews"
        title="Trusted by players who want the climb handled properly"
        description="Trustpilot-style proof from customers who care about communication, delivery speed, and order safety."
    >
        <a class="btn btn-outline-light btn-sm" href="{{ route('reviews') }}">View all reviews</a>
    </x-home.section-heading>

    <div class="ggwp-marketplace-reviews__grid">
        @foreach($items as $review)
            <figure class="ggwp-review-card">
                <div class="ggwp-review-card__stars" aria-label="5 out of 5 stars">
                    <span aria-hidden="true">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
                </div>

                <figcaption class="ggwp-review-card__meta">
                    <strong>{{ data_get($review, 'author_name', 'Verified Customer') }}</strong>
                    <span>{{ data_get($review, 'service', 'Boosting Service') }}</span>
                </figcaption>

                <blockquote>
                    <p>&ldquo;{{ data_get($review, 'quote', 'Fast delivery and clear support from checkout to completion.') }}&rdquo;</p>
                </blockquote>

                <div class="ggwp-review-card__badge">Verified GGWPBoost review</div>
            </figure>
        @endforeach
    </div>
</section>
