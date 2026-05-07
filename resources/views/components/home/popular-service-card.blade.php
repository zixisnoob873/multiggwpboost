@props([
    'service',
])

@php
    $name = data_get($service, 'name', 'Service');
    $gameLabel = data_get($service, 'gameShortName', data_get($service, 'gameName', 'Multi-game'));
    $ctaUrl = data_get($service, 'ctaUrl', data_get($service, 'url', route('checkout')));
@endphp

<article class="ggwp-popular-service-card">
    <div class="ggwp-popular-service-card__icon" aria-hidden="true">
        {{ data_get($service, 'icon', 'GG') }}
    </div>

    <div class="ggwp-popular-service-card__copy">
        <span>{{ $gameLabel }}</span>
        <h3>{{ $name }}</h3>
        <p>{{ data_get($service, 'description', 'Professional delivery with secure checkout and support visibility.') }}</p>
    </div>

    <div class="ggwp-popular-service-card__footer">
        <span>{{ data_get($service, 'startingPriceLabel', 'Custom quote') }}</span>
        <a class="btn btn-outline-light btn-sm" href="{{ $ctaUrl }}" aria-label="Order {{ $name }}">
            Order
        </a>
    </div>
</article>
