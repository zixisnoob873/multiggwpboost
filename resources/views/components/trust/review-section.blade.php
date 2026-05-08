@props([
    'reviews' => [],
    'id' => 'trustReviewsHeading',
    'kicker' => 'Reviews',
    'title' => 'Trusted by players who want the climb handled properly',
    'description' => 'Trustpilot-style proof from customers who care about communication, delivery speed, and order safety.',
    'serviceFallback' => 'Boosting Service',
    'limit' => 3,
    'showCta' => true,
])

@php
    $items = collect($reviews)->take((int) $limit)->values();
@endphp

@if($items->isNotEmpty())
    <section {{ $attributes->class('section-block ggwp-trust-section ggwp-trust-reviews') }} aria-labelledby="{{ $id }}" data-conversion-component="review-section">
        <x-home.section-heading
            :id="$id"
            :kicker="$kicker"
            :title="$title"
            :description="$description"
        >
            @if($showCta)
                <a class="btn btn-outline-light btn-sm" href="{{ route('reviews') }}">View all reviews</a>
            @endif
        </x-home.section-heading>

        <div class="ggwp-trust-reviews__grid">
            @foreach($items as $review)
                <x-trust.review-card :review="$review" :service-fallback="$serviceFallback" />
            @endforeach
        </div>
    </section>
@endif
