@extends('layouts.layout')

@section('main_classes', 'container site-main ggwp-category-page')

@php
    $games = collect($categoryGames ?? []);
    $services = collect($categoryServices ?? []);
    $faqs = collect($faqs ?? []);
    $reviews = collect($reviews ?? []);
    $categoryName = $category?->name ?? 'Game Category';
    $categoryDescription = $category?->description ?: 'Compare active games, service pages, pricing paths, and checkout options for this category.';
@endphp

@section('content')
    <header class="ggwp-game-hero" aria-labelledby="categoryPageTitle">
        <div class="ggwp-game-hero__copy">
            <span class="ggwp-game-kicker">GGWPBoost category</span>
            <div>
                <h1 id="categoryPageTitle">{{ $categoryName }} Boosting Services</h1>
                <p>{{ $categoryDescription }}</p>
            </div>

            <div class="ggwp-game-hero__actions" aria-label="{{ $categoryName }} category actions">
                <a class="btn btn-danger" href="#category-games">Browse Games</a>
                <a class="btn btn-outline-light" href="#category-services">Compare Services</a>
                <a class="btn btn-outline-light ggwp-live-chat-cta" href="{{ route('contact') }}#contactForm" data-live-chat-trigger>Live Chat</a>
            </div>
        </div>

        <aside class="ggwp-game-hero__panel" aria-label="{{ $categoryName }} category summary">
            <dl class="ggwp-game-hero__stats">
                <div>
                    <dt>{{ $games->count() }}+</dt>
                    <dd>Active games</dd>
                </div>
                <div>
                    <dt>{{ $services->count() }}+</dt>
                    <dd>Services</dd>
                </div>
                <div>
                    <dt>24/7</dt>
                    <dd>Support</dd>
                </div>
            </dl>
            <div class="ggwp-game-hero__review">
                <strong>Built for comparison</strong>
                <p>Use category pages to move from broad game research into specific service pages without losing the order path.</p>
            </div>
        </aside>
    </header>

    <x-trust.badge-strip :label="$categoryName.' category trust badges'" />

    <section id="category-games" class="section-block ggwp-game-section ggwp-game-related" aria-labelledby="categoryGamesHeading">
        <x-home.section-heading
            id="categoryGamesHeading"
            :kicker="$categoryName.' games'"
            title="Active Games"
            description="Choose the game page that matches your goal, then compare its available services."
        />

        <div class="ggwp-game-related__grid">
            @foreach($games as $game)
                <article class="ggwp-game-related-card">
                    <span class="ggwp-game-kicker">{{ data_get($game, 'category.name', $categoryName) }}</span>
                    <h3>{{ data_get($game, 'name', 'Game') }}</h3>
                    <p>{{ data_get($game, 'description', 'View active services, pricing, and checkout options.') }}</p>
                    <a class="btn btn-outline-light btn-sm" href="{{ data_get($game, 'url', route('home')) }}">
                        View {{ data_get($game, 'shortName', data_get($game, 'name', 'game')) }}
                    </a>
                </article>
            @endforeach
        </div>
    </section>

    <section id="category-services" class="section-block ggwp-game-section ggwp-game-services" aria-labelledby="categoryServicesHeading">
        <x-home.section-heading
            id="categoryServicesHeading"
            :kicker="$categoryName.' services'"
            title="Available Services"
            description="Open a service page for page-specific details, related options, checkout, and support."
        />

        @if($services->isNotEmpty())
            <div class="ggwp-game-services__grid">
                @foreach($services as $service)
                    <x-game.service-card :service="$service" />
                @endforeach
            </div>
        @else
            <p class="ggwp-game-empty">No published services are available in this category yet.</p>
        @endif
    </section>

    <x-trust.faq-accordion
        :id="'categoryFaqAccordion'.\Illuminate\Support\Str::studly($categoryName)"
        heading-id="categoryFaqHeading"
        :faqs="$faqs"
        :kicker="$categoryName.' FAQ'"
        title="Questions before you choose a game"
        description="Answers about safety, delivery, support, and checkout before opening a game-specific service."
    />
    <x-trust.review-section
        id="categoryReviewsHeading"
        :reviews="$reviews"
        kicker="Reviews"
        :title="$categoryName.' customer proof'"
        description="Global marketplace proof shown on category pages without duplicating review markup."
    />
    <x-trust.live-chat-cta
        id="categoryLiveChatCtaHeading"
        :title="'Need help comparing '.$categoryName.' services?'"
        body="Open live chat and we will help you choose the right game page, service type, and order path."
    />
@endsection
