@props([
    'review' => [],
    'rating' => 5,
    'serviceFallback' => 'Boosting Service',
])

@php
    $author = data_get($review, 'author_name', data_get($review, 'author', 'Verified Customer'));
    $service = data_get($review, 'service', $serviceFallback);
    $quote = data_get($review, 'quote', 'Fast delivery, clear support, and helpful updates from checkout to completion.');
@endphp

<figure {{ $attributes->class('ggwp-trust-review-card') }} data-conversion-component="review-card">
    <x-trust.star-rating :rating="$rating" />

    <figcaption class="ggwp-trust-review-card__meta">
        <strong>{{ $author }}</strong>
        <span>{{ $service }}</span>
    </figcaption>

    <blockquote>
        <p>&ldquo;{{ $quote }}&rdquo;</p>
    </blockquote>

    <div class="ggwp-trust-review-card__badge">Verified GGWPBoost review</div>
</figure>
