@props([
    'game' => [],
    'card' => [],
    'description' => null,
    'serviceCount' => 0,
])

@php
    $slug = data_get($game, 'slug', 'valorant');
    $name = data_get($game, 'name', data_get($card, 'name', 'Game'));
    $shortName = data_get($game, 'shortName', data_get($card, 'shortName', $name));
    $copy = $description ?: data_get($game, 'description', "Order {$name} boosting services with professional boosters and secure checkout.");
    $rating = number_format((float) data_get($game, 'metadata.rating', 4.9), 1);
    $reviewCount = (int) data_get($game, 'metadata.review_count', 1200);
    $startingPrice = data_get($card, 'startingPriceLabel', 'Custom quote');
    $imageUrl = data_get($card, 'imageUrl');
    $initials = data_get($card, 'initials', 'GG');
    $categoryName = data_get($game, 'category.name');
    $categoryUrl = data_get($game, 'category.url', data_get($card, 'category.url'));
@endphp

<header class="ggwp-game-hero" aria-labelledby="gamePageTitle">
    <div class="ggwp-game-hero__copy">
        @if($categoryName && $categoryUrl)
            <a class="ggwp-game-kicker ggwp-game-kicker--link" href="{{ $categoryUrl }}">{{ $categoryName }}</a>
        @else
            <span class="ggwp-game-kicker">GGWPBoost game marketplace</span>
        @endif
        <div class="ggwp-game-hero__identity">
            <div class="ggwp-game-logo" aria-hidden="{{ $imageUrl ? 'false' : 'true' }}">
                @if($imageUrl)
                    <img src="{{ $imageUrl }}" alt="{{ $name }} logo" decoding="async" fetchpriority="high">
                @else
                    <span>{{ $initials }}</span>
                @endif
            </div>
            <div>
                <h1 id="gamePageTitle">{{ $name }} Boosting Services</h1>
                <p>{{ $copy }}</p>
            </div>
        </div>

        <div class="ggwp-game-hero__actions" aria-label="{{ $name }} order actions">
            <a class="btn btn-danger" href="#available-services" data-conversion-cta="game-primary" data-analytics-context="game_hero" data-analytics-game-slug="{{ $slug }}" data-analytics-game-name="{{ $name }}">Choose a service</a>
            <a class="btn btn-outline-light" href="{{ route('checkout', ['game' => $slug]) }}" data-conversion-cta="game-checkout-defaults" data-analytics-context="game_hero" data-analytics-game-slug="{{ $slug }}" data-analytics-game-name="{{ $name }}">Checkout defaults</a>
            <a class="btn btn-outline-light ggwp-live-chat-cta" href="{{ route('contact') }}#contactForm" data-live-chat-trigger data-analytics-context="game_hero" data-analytics-game-slug="{{ $slug }}" data-analytics-game-name="{{ $name }}">Live Chat</a>
        </div>
    </div>

    <aside class="ggwp-game-hero__panel" aria-label="{{ $name }} service highlights">
        <dl class="ggwp-game-hero__stats">
            <div>
                <dt>{{ $rating }}/5</dt>
                <dd>Customer rating</dd>
            </div>
            <div>
                <dt>{{ $startingPrice }}</dt>
                <dd>Starting price</dd>
            </div>
            <div>
                <dt>{{ max(1, (int) $serviceCount) }}+</dt>
                <dd>{{ $shortName }} services</dd>
            </div>
        </dl>

        <div class="ggwp-game-hero__review">
            <x-trust.star-rating :rating="$rating" />
            <strong>Trusted by competitive players</strong>
            <p>{{ number_format($reviewCount) }}+ marketplace customers use GGWPBoost for safe order handling, clear support, and fast delivery. Choose a service below to configure pricing first.</p>
        </div>
    </aside>
</header>
