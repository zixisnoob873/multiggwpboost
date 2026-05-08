@extends('layouts.layout')

@section('main_classes', 'container site-main ggwp-game-page ggwp-service-category-page')

@php
    $category = $serviceCategory ?? [];
    $games = collect($categoryGames ?? []);
    $services = collect($categoryServices ?? []);
    $faqs = collect($faqs ?? []);
    $reviews = collect($reviews ?? []);
    $relatedCategories = collect($relatedServiceCategories ?? []);
    $categoryName = data_get($category, 'name', 'Service');
    $categorySlug = data_get($category, 'slug', 'service-category');
    $firstService = $services->first();
@endphp

@section('content')
    <header class="ggwp-game-hero" aria-labelledby="serviceCategoryPageTitle">
        <div class="ggwp-game-hero__copy">
            <span class="ggwp-game-kicker">GGWPBoost service category</span>
            <div class="ggwp-game-hero__identity">
                <h1 id="serviceCategoryPageTitle">{{ $categoryName }} Services</h1>
                <p>{{ data_get($category, 'description', 'Compare supported games, starting prices, and exact service pages for this service category.') }}</p>
            </div>

            <div class="ggwp-game-hero__actions" aria-label="{{ $categoryName }} category actions">
                <a class="btn btn-danger" href="#category-services">Compare {{ $categoryName }} services</a>
                @if($firstService)
                    <a class="btn btn-outline-light" href="{{ data_get($firstService, 'url') }}">
                        Open {{ data_get($firstService, 'gameShortName', data_get($firstService, 'gameName', 'game')) }} service
                    </a>
                @endif
            </div>
        </div>

        <aside class="ggwp-game-hero__panel" aria-label="{{ $categoryName }} category summary">
            <dl class="ggwp-game-hero__stats">
                <div>
                    <dt>{{ data_get($category, 'startingPriceLabel', 'Custom quote') }}</dt>
                    <dd>Starting price</dd>
                </div>
                <div>
                    <dt>{{ data_get($category, 'gameCount', $games->count()) }}+</dt>
                    <dd>Supported games</dd>
                </div>
                <div>
                    <dt>{{ data_get($category, 'serviceCount', $services->count()) }}+</dt>
                    <dd>Exact service pages</dd>
                </div>
            </dl>
            <div class="ggwp-game-hero__review">
                <strong>Category-matched services</strong>
                <p>Every card is generated from published catalog services, so the links stay aligned with live game pages and checkout context.</p>
            </div>
        </aside>
    </header>

    <x-trust.badge-strip :label="$categoryName.' trust badges'" />

    <section class="section-block ggwp-game-section" aria-labelledby="serviceCategoryOverviewHeading">
        <x-home.section-heading
            id="serviceCategoryOverviewHeading"
            kicker="Overview"
            :title="'What '.$categoryName.' covers'"
            :description="data_get($category, 'summary', data_get($category, 'description'))"
        />
    </section>

    <section id="category-games" class="section-block ggwp-game-section ggwp-game-related" aria-labelledby="categoryGamesHeading">
        <x-home.section-heading
            id="categoryGamesHeading"
            :kicker="$categoryName.' games'"
            title="Games offering this service"
            description="Choose a game first if you want to compare its full service lineup before opening a category-specific order path."
        />

        <div class="ggwp-game-related__grid">
            @foreach($games as $game)
                <article class="ggwp-game-related-card">
                    <span class="ggwp-game-kicker">{{ data_get($game, 'category.name', 'Game') }}</span>
                    <h3>{{ data_get($game, 'name', 'Game') }}</h3>
                    <p>{{ data_get($game, 'description', 'View active services, pricing, and checkout options for this game.') }}</p>
                    <a class="btn btn-outline-light btn-sm" href="{{ data_get($game, 'url', route('home')) }}">
                        View {{ data_get($game, 'shortName', data_get($game, 'name', 'game')) }} services
                    </a>
                </article>
            @endforeach
        </div>
    </section>

    <section id="category-services" class="section-block ggwp-game-section ggwp-game-services" aria-labelledby="categoryServicesHeading">
        <x-home.section-heading
            id="categoryServicesHeading"
            :kicker="$categoryName.' service pages'"
            title="Game service cards"
            description="Open the exact service page for game-specific pricing, add-ons, FAQ, reviews, and checkout details."
        />

        <div class="ggwp-game-services__grid">
            @foreach($services as $service)
                <x-game.service-card
                    :service="$service"
                    secondary-label="Open service page"
                />
            @endforeach
        </div>
    </section>

    <x-trust.faq-accordion
        :id="'serviceCategoryFaqAccordion'.\Illuminate\Support\Str::studly($categorySlug)"
        heading-id="serviceCategoryFaqHeading"
        :faqs="$faqs"
        :kicker="$categoryName.' FAQ'"
        title="Questions before you choose a game"
        description="Answers for service availability, price floors, and how category pages connect to exact game services."
    />

    <x-trust.review-section
        id="serviceCategoryReviewsHeading"
        :reviews="$reviews"
        kicker="Reviews"
        :title="$categoryName.' customer proof'"
        description="Short reviews generated from matching catalog services so the proof stays tied to supported games."
        :service-fallback="$categoryName"
    />

    <x-trust.discord-cta
        id="serviceCategoryDiscordCtaHeading"
        :title="'Ask about '.$categoryName.' on Discord'"
        body="Use Discord support for custom scope questions before choosing the exact game service page."
    />

    @if($relatedCategories->isNotEmpty())
        <section class="section-block ggwp-game-section ggwp-game-related" aria-labelledby="relatedServiceCategoriesHeading">
            <x-home.section-heading
                id="relatedServiceCategoriesHeading"
                kicker="Related categories"
                title="Related service categories"
                description="Move sideways into nearby service types when your goal needs a different kind of delivery."
            />

            <div class="ggwp-game-related__grid">
                @foreach($relatedCategories as $relatedCategory)
                    <article class="ggwp-game-related-card">
                        <span class="ggwp-game-kicker">{{ data_get($relatedCategory, 'serviceCount', 0) }} services</span>
                        <h3>{{ data_get($relatedCategory, 'name', 'Service category') }}</h3>
                        <p>{{ data_get($relatedCategory, 'description', 'Compare related service pages across supported games.') }}</p>
                        <a class="btn btn-outline-light btn-sm" href="{{ data_get($relatedCategory, 'url', route('home')) }}">
                            View {{ data_get($relatedCategory, 'name', 'category') }}
                        </a>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
@endsection
