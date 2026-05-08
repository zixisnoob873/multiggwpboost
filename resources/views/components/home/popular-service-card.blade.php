@props([
    'service',
])

@php
    $name = data_get($service, 'name', 'Service');
    $gameLabel = data_get($service, 'gameShortName', data_get($service, 'gameName', 'Multi-game'));
    $serviceUrl = data_get($service, 'url', data_get($service, 'ctaUrl', route('checkout')));
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
        <a
            class="btn btn-danger btn-sm"
            href="{{ $serviceUrl }}"
            aria-label="Configure {{ $name }}"
            data-analytics-service-card
            data-analytics-context="popular_services"
            data-analytics-service-slug="{{ data_get($service, 'slug') }}"
            data-analytics-service-name="{{ $name }}"
            data-analytics-game-slug="{{ data_get($service, 'gameSlug') }}"
            data-analytics-game-name="{{ data_get($service, 'gameName', $gameLabel) }}"
        >
            Configure
        </a>
    </div>
</article>
