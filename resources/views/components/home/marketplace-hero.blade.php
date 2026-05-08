@props([
    'tagline' => 'GGWPBoost — Premium Boosting Across Every Competitive Game.',
    'games' => [],
    'services' => [],
    'hero' => [],
])

@php
    $featuredGames = collect($games)->take(4);
    $featuredServices = collect($services)->take(4);
    $heroContent = is_array($hero) ? $hero : [];
    $eyebrow = data_get($heroContent, 'eyebrow', $tagline);
    $headline = data_get($heroContent, 'headline', 'Premium Game Boosting Services for Every Competitive Title');
    $description = data_get($heroContent, 'description', 'Rank up faster with professional boosters across VALORANT, League, CS2, Apex Legends, Call of Duty, Overwatch 2, and more.');
@endphp

<section class="ggwp-marketplace-hero" aria-labelledby="homeHeroHeading">
    <div class="ggwp-marketplace-hero__copy">
        <span class="ggwp-marketplace-hero__tagline">{{ $eyebrow }}</span>
        <h1 id="homeHeroHeading">{{ $headline }}</h1>
        <p class="ggwp-marketplace-hero__subhead">
            {{ $description }}
        </p>

        <div class="ggwp-marketplace-hero__actions" aria-label="Homepage actions">
            <a class="btn btn-danger ggwp-premium-cta" href="{{ route('home') }}#featured-games" data-conversion-cta="home-primary" data-analytics-event="browse_games_click" data-analytics-context="homepage_hero" data-analytics-label="choose_game" data-browse-games>Choose your game</a>
            <a class="btn btn-outline-light" href="{{ route('home') }}#popular-services" data-conversion-cta="home-secondary" data-analytics-context="homepage_hero" data-analytics-label="compare_services">Compare services</a>
            <a class="btn btn-outline-light ggwp-live-chat-cta" href="{{ route('contact') }}#contactForm" data-live-chat-trigger data-analytics-context="homepage_hero">Live Chat</a>
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
            <strong>Pick a game, then configure the exact service</strong>
        </div>

        <div class="ggwp-marketplace-hero__game-list">
            @foreach($featuredGames as $game)
                <a
                    class="ggwp-marketplace-hero__game"
                    href="{{ data_get($game, 'url', route('home')) }}"
                    data-analytics-game-card
                    data-analytics-context="homepage_hero"
                    data-analytics-game-slug="{{ data_get($game, 'slug') }}"
                    data-analytics-game-name="{{ data_get($game, 'name', 'Featured game') }}"
                >
                    <span class="ggwp-marketplace-hero__game-mark" aria-hidden="true">{{ data_get($game, 'initials', 'GG') }}</span>
                    <span>
                        <strong>{{ data_get($game, 'name', 'Featured game') }}</strong>
                        <small>From {{ data_get($game, 'startingPriceLabel', 'Custom quote') }} before add-ons</small>
                    </span>
                </a>
            @endforeach
        </div>

        <div class="ggwp-marketplace-hero__service-strip" aria-label="Popular service shortcuts">
            @foreach($featuredServices as $service)
                <a
                    href="{{ data_get($service, 'url', route('checkout')) }}"
                    data-analytics-service-card
                    data-analytics-context="homepage_hero"
                    data-analytics-service-slug="{{ data_get($service, 'slug') }}"
                    data-analytics-service-name="{{ data_get($service, 'name', 'Service') }}"
                    data-analytics-game-slug="{{ data_get($service, 'gameSlug') }}"
                    data-analytics-game-name="{{ data_get($service, 'gameName') }}"
                >{{ data_get($service, 'name', 'Service') }}</a>
            @endforeach
        </div>
    </aside>
</section>
