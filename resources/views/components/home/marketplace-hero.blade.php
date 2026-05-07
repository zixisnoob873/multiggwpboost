@props([
    'tagline' => 'GGWPBoost — Premium Boosting Across Every Competitive Game.',
    'games' => [],
    'services' => [],
])

@php
    $featuredGames = collect($games)->take(4);
    $featuredServices = collect($services)->take(4);
@endphp

<section class="ggwp-marketplace-hero" aria-labelledby="homeHeroHeading">
    <div class="ggwp-marketplace-hero__copy">
        <span class="ggwp-marketplace-hero__tagline">{{ $tagline }}</span>
        <h1 id="homeHeroHeading">Premium Game Boosting Services for Every Competitive Title</h1>
        <p class="ggwp-marketplace-hero__subhead">
            Rank up faster with professional boosters across VALORANT, League, CS2, Apex Legends, Call of Duty, Overwatch 2, and more.
        </p>

        <div class="ggwp-marketplace-hero__actions" aria-label="Homepage actions">
            <a class="btn btn-danger ggwp-premium-cta" href="{{ route('checkout') }}">Order Now</a>
            <a class="btn btn-outline-light" href="{{ route('home') }}#featured-games">Browse Games</a>
            <a class="btn btn-outline-light ggwp-live-chat-cta" href="{{ route('contact') }}#contactForm" data-live-chat-trigger>Live Chat</a>
        </div>

        <dl class="ggwp-marketplace-hero__proof" aria-label="Marketplace proof points">
            <div>
                <dt>{{ number_format(max(8, count($games))) }}+</dt>
                <dd>active games</dd>
            </div>
            <div>
                <dt>{{ number_format(max(7, count($services))) }}+</dt>
                <dd>service types</dd>
            </div>
            <div>
                <dt>24/7</dt>
                <dd>support access</dd>
            </div>
        </dl>
    </div>

    <aside class="ggwp-marketplace-hero__panel" aria-label="Featured marketplace preview">
        <div class="ggwp-marketplace-hero__panel-head">
            <span class="ggwp-home-section-kicker">Live Marketplace</span>
            <strong>Popular games ready for checkout</strong>
        </div>

        <div class="ggwp-marketplace-hero__game-list">
            @foreach($featuredGames as $game)
                <a class="ggwp-marketplace-hero__game" href="{{ data_get($game, 'url', route('home')) }}">
                    <span class="ggwp-marketplace-hero__game-mark" aria-hidden="true">{{ data_get($game, 'initials', 'GG') }}</span>
                    <span>
                        <strong>{{ data_get($game, 'name', 'Featured game') }}</strong>
                        <small>From {{ data_get($game, 'startingPriceLabel', 'Custom quote') }}</small>
                    </span>
                </a>
            @endforeach
        </div>

        <div class="ggwp-marketplace-hero__service-strip" aria-label="Popular service shortcuts">
            @foreach($featuredServices as $service)
                <a href="{{ data_get($service, 'url', route('checkout')) }}">{{ data_get($service, 'name', 'Service') }}</a>
            @endforeach
        </div>
    </aside>
</section>
