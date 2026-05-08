@extends('layouts.layout')

@section('main_classes', 'container site-main ggwp-service-page')

@php
    $game = $activeGame ?? [];
    $service = $activeService ?? [];
    $hero = $serviceHero ?? [];
    $serviceName = data_get($service, 'name', 'Boosting Service');
    $gameShortName = data_get($game, 'shortName', data_get($game, 'name', 'Game'));
    $relatedServices = collect($relatedServices ?? []);
    $relatedBlogArticles = collect($relatedBlogArticles ?? []);
    $orderSteps = collect($orderProcessSteps ?? []);
    $serviceStartingPrice = data_get($hero, 'startingPriceLabel', data_get($serviceCard ?? [], 'startingPriceLabel', 'Custom quote'));
@endphp

@section('content')
    <header class="ggwp-service-hero" aria-labelledby="servicePageTitle">
        <div class="ggwp-service-hero__copy">
            <span class="ggwp-game-kicker">{{ data_get($hero, 'eyebrow', $gameShortName.' service') }}</span>
            <div>
                <h1 id="servicePageTitle">{{ data_get($hero, 'title', $gameShortName.' '.$serviceName) }}</h1>
                <p class="ggwp-service-hero__headline">{{ data_get($hero, 'headline') }}</p>
                <p class="ggwp-service-hero__subheadline">{{ data_get($hero, 'subheadline') }}</p>
            </div>
            <div class="ggwp-service-hero__actions">
                <a
                    class="btn btn-danger btn-lg"
                    href="{{ data_get($hero, 'ctaUrl', '#serviceCalculator') }}"
                    data-conversion-cta="service-hero-start"
                    data-analytics-context="service_hero"
                    data-analytics-service-slug="{{ data_get($service, 'slug') }}"
                    data-analytics-service-name="{{ $serviceName }}"
                    data-analytics-game-slug="{{ data_get($game, 'slug') }}"
                    data-analytics-game-name="{{ data_get($game, 'name', $gameShortName) }}"
                >Start Order</a>
                <a
                    class="btn btn-outline-light btn-lg"
                    href="{{ route('game.show', ['game' => data_get($game, 'slug', 'valorant')]) }}"
                    data-analytics-service-card
                    data-analytics-context="service_hero"
                    data-analytics-label="compare_game_services"
                    data-analytics-service-slug="{{ data_get($service, 'slug') }}"
                    data-analytics-service-name="{{ $serviceName }}"
                    data-analytics-game-slug="{{ data_get($game, 'slug') }}"
                    data-analytics-game-name="{{ data_get($game, 'name', $gameShortName) }}"
                >
                    Compare {{ $gameShortName }} services
                </a>
            </div>
        </div>

        <aside class="ggwp-service-hero__panel" aria-label="{{ $serviceName }} service highlights">
            <dl class="ggwp-game-hero__stats">
                <div>
                    <dt>{{ data_get($hero, 'startingPriceLabel', 'Custom quote') }}</dt>
                    <dd>Starting price</dd>
                </div>
                <div>
                    <dt>{{ data_get($hero, 'ratingLabel', '5.0 / 5') }}</dt>
                    <dd>Rating</dd>
                </div>
                <div>
                    <dt>{{ data_get($hero, 'reviewLabel', 'Verified reviews') }}</dt>
                    <dd>Reviews</dd>
                </div>
            </dl>
            <div class="ggwp-game-hero__review">
                <x-trust.star-rating :rating="5" />
                <strong>{{ $serviceName }} for {{ $gameShortName }}</strong>
                <p>Built around clear scope, validated pricing, and checkout details that are recalculated before payment.</p>
            </div>
        </aside>
    </header>

    <x-trust.badge-strip :label="$serviceName.' trust badges'" />

    <x-service.calculator :config="$serviceCalculator ?? []" :estimated-delivery="$estimatedDelivery ?? []" />

    <x-trust.order-process
        id="serviceHowHeading"
        class="ggwp-service-how"
        :steps="$orderSteps"
        kicker="How It Works"
        :title="'How '.$serviceName.' works'"
        :description="'A short, predictable flow keeps '.$gameShortName.' orders easy to start and easy to monitor.'"
    />

    <section class="section-block ggwp-game-section ggwp-service-delivery" aria-labelledby="serviceDeliveryHeading">
        <div class="card app-card">
            <div class="card-body p-4 p-xl-5">
                <span class="ggwp-home-section-kicker">Estimated Delivery</span>
                <h2 id="serviceDeliveryHeading" class="h3 mt-2">{{ data_get($estimatedDelivery ?? [], 'label', 'Confirmed after order review') }}</h2>
                <p class="text-secondary mb-0">{{ data_get($estimatedDelivery ?? [], 'description') }}</p>
            </div>
        </div>
    </section>

    <x-trust.faq-accordion
        :id="'serviceFaqAccordion'.\Illuminate\Support\Str::studly(data_get($service, 'slug', 'service'))"
        heading-id="serviceFaqHeading"
        :faqs="$faqs ?? []"
        :kicker="$serviceName.' FAQ'"
        :title="'Questions before you order '.$serviceName"
        :description="'Safety, delivery, play mode, and VPN answers for '.$gameShortName.' '.$serviceName.' orders.'"
    />
    <x-trust.review-section
        id="serviceReviewsHeading"
        :reviews="$reviews ?? []"
        kicker="Reviews"
        :title="$serviceName.' customer proof'"
        description="Short, scan-friendly reviews focused on support quality, speed, and delivery confidence."
        :service-fallback="$gameShortName.' '.$serviceName"
    />
    <x-trust.live-chat-cta
        id="serviceLiveChatCtaHeading"
        :title="'Need help with '.$serviceName.'?'"
        body="Open live chat before checkout and we will help confirm scope, delivery mode, and eligible add-ons."
    />

    <aside class="ggwp-service-mobile-cta" data-service-mobile-cta aria-label="Start {{ $serviceName }} order">
        <div class="ggwp-service-mobile-cta__copy">
            <span>{{ $serviceStartingPrice }}</span>
            <strong>{{ $gameShortName }} {{ $serviceName }}</strong>
        </div>
        <a
            class="btn btn-danger"
            href="#serviceCalculator"
            data-conversion-cta="service-mobile"
            data-analytics-context="service_mobile_cta"
            data-analytics-service-slug="{{ data_get($service, 'slug') }}"
            data-analytics-service-name="{{ $serviceName }}"
            data-analytics-game-slug="{{ data_get($game, 'slug') }}"
            data-analytics-game-name="{{ data_get($game, 'name', $gameShortName) }}"
        >
            Configure order
        </a>
    </aside>

    @if($relatedServices->isNotEmpty())
        <section class="section-block ggwp-game-section ggwp-game-related" aria-labelledby="relatedServicesHeading">
            <x-home.section-heading
                id="relatedServicesHeading"
                kicker="Related"
                :title="'Related '.$gameShortName.' services'"
                description="Compare nearby services before checkout if your goal needs a slightly different scope."
            />

            <div class="ggwp-game-related__grid">
                @foreach($relatedServices as $relatedService)
                    <article class="ggwp-game-related-card">
                        <span class="ggwp-game-kicker">{{ $relatedService['gameShortName'] ?? $gameShortName }}</span>
                        <h3>{{ $relatedService['name'] ?? 'Service' }}</h3>
                        @if(! empty($relatedService['description']))
                            <p>{{ $relatedService['description'] }}</p>
                        @endif
                        <a
                            class="btn btn-outline-light btn-sm"
                            href="{{ $relatedService['url'] ?? route('game.show', ['game' => data_get($game, 'slug', 'valorant')]) }}"
                            data-analytics-service-card
                            data-analytics-context="related_services"
                            data-analytics-service-slug="{{ $relatedService['slug'] ?? '' }}"
                            data-analytics-service-name="{{ $relatedService['name'] ?? 'Service' }}"
                            data-analytics-game-slug="{{ $relatedService['gameSlug'] ?? data_get($game, 'slug') }}"
                            data-analytics-game-name="{{ $relatedService['gameName'] ?? data_get($game, 'name', $gameShortName) }}"
                        >
                            View {{ $relatedService['name'] ?? 'service' }}
                        </a>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if($relatedBlogArticles->isNotEmpty())
        <section class="section-block ggwp-game-section ggwp-game-related" aria-labelledby="relatedGuidesHeading">
            <x-home.section-heading
                id="relatedGuidesHeading"
                kicker="Guides"
                :title="$gameShortName.' service guides'"
                description="Read a related guide before checkout when you want more context on safety, timing, pricing, or service fit."
            />

            <div class="ggwp-game-related__grid">
                @foreach($relatedBlogArticles as $article)
                    <article class="ggwp-game-related-card">
                        <span class="ggwp-game-kicker">{{ $article->published_at?->format('M j, Y') ?? 'Guide' }}</span>
                        <h3>{{ $article->title }}</h3>
                        <p>{{ $article->excerpt }}</p>
                        <a class="btn btn-outline-light btn-sm" href="{{ route('blog.show', ['slug' => $article->slug]) }}">
                            Read guide
                        </a>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
@endsection
