@props([
    'service' => [],
    'primaryLabel' => 'Configure',
    'secondaryLabel' => 'Checkout',
])

@php
    $name = data_get($service, 'name', 'Boosting Service');
    $description = data_get($service, 'description', 'Configure this service and continue to secure checkout.');
    $gameShortName = data_get($service, 'gameShortName', 'Game');
    $serviceUrl = data_get($service, 'url', route('checkout'));
    $checkoutUrl = data_get($service, 'ctaUrl', route('checkout'));
    $price = data_get($service, 'startingPriceLabel', 'Custom quote');
@endphp

<article class="ggwp-game-service-card">
    <div class="ggwp-game-service-card__head">
        <span class="ggwp-game-kicker">{{ $gameShortName }}</span>
        <h3>{{ $name }}</h3>
    </div>

    <p>{{ $description }}</p>

    <div class="ggwp-game-service-card__footer">
        <div>
            <span>Starting at</span>
            <strong>{{ $price }}</strong>
        </div>
        <div class="ggwp-game-service-card__actions">
            <a
                class="btn btn-danger btn-sm"
                href="{{ $serviceUrl }}"
                data-analytics-service-card
                data-analytics-context="game_service_card"
                data-analytics-service-slug="{{ data_get($service, 'slug') }}"
                data-analytics-service-name="{{ $name }}"
                data-analytics-game-slug="{{ data_get($service, 'gameSlug') }}"
                data-analytics-game-name="{{ data_get($service, 'gameName', $gameShortName) }}"
            >{{ $primaryLabel }}</a>
            <a
                class="btn btn-outline-light btn-sm"
                href="{{ $checkoutUrl }}"
                data-analytics-service-card
                data-analytics-context="game_service_card"
                data-analytics-label="checkout_shortcut"
                data-analytics-service-slug="{{ data_get($service, 'slug') }}"
                data-analytics-service-name="{{ $name }}"
                data-analytics-game-slug="{{ data_get($service, 'gameSlug') }}"
                data-analytics-game-name="{{ data_get($service, 'gameName', $gameShortName) }}"
            >{{ $secondaryLabel }}</a>
        </div>
    </div>
</article>
