@extends('layouts.layout')

@php
    $rankOptions = $ggwpRankOptions ?? [];
    $rankOptionsWithRadiant = $ggwpRankOptionsWithRadiant ?? [];
    $regions = $ggwpRegions ?? [];
    $platforms = $ggwpPlatforms ?? [];
    $boostModeOptions = $ggwpBoostModeOptions ?? [];
    $averageRrOptionChoices = $ggwpAverageRrOptionChoices ?? [];
    $selfPlayBoostModeLabel = collect($boostModeOptions)->firstWhere('value', 'self_play')['label'] ?? 'Duo / Self-Play';
    $gameShortName = $ggwpGame['shortName'] ?? data_get($activeGame ?? [], 'shortName', 'VALORANT');
    $gameName = $ggwpGame['name'] ?? data_get($activeGame ?? [], 'name', $gameShortName);
    $primaryServiceName = $ggwpServiceOptions[0] ?? 'Rank Boosting';
    $serviceTabs = $serviceTabs ?? [];
@endphp

@push('head')
    <meta name="cryptomus" content="fdcccf04" />
@endpush

@section('main_classes', ($isMarketplaceLanding ?? false) ? 'container site-main ggwp-marketplace-home-shell' : 'container site-main')

@section('content')
  @if($isMarketplaceLanding ?? false)
    <x-home.marketplace-hero
      :tagline="$marketplaceTagline ?? 'GGWPBoost — Premium Boosting Across Every Competitive Game.'"
      :games="$featuredGames ?? []"
      :services="$popularServices ?? []"
    />

    <x-trust.badge-strip class="ggwp-marketplace-proof-strip" />
    <x-home.featured-games-grid :games="$featuredGames ?? []" />
    <x-home.popular-services :services="$popularServices ?? []" />
    <x-home.why-choose :items="$whyChooseItems ?? []" />
    <x-home.review-grid :reviews="$reviews ?? collect()" />
    <x-home.faq-list :faqs="$marketplaceFaqs ?? []" />
    <x-trust.live-chat-cta
      id="homeLiveChatCtaHeading"
      title="Need help choosing a service?"
      body="Tell us your game, goal, and delivery preference. Support will point you to the safest order path before checkout."
    />
    <x-trust.discord-cta
      id="homeDiscordCtaHeading"
      title="Prefer Discord support?"
      body="Join the GGWPBoost Discord for quick custom questions, service comparisons, and order guidance."
    />
    @include('home.partials.latest-blogs')
  @else
    @include('home.partials.hero')

    <x-trust.badge-strip class="ggwp-trust-strip" />

    @include('home.partials.marketplace-highlights')

    <section id="services" class="section-block ggwp-section-anchor ggwp-home-services" aria-labelledby="servicesHeading">
      <div class="ggwp-home-section-header">
        <div>
          <span class="ggwp-home-section-kicker">Live Pricing</span>
          <h2 id="servicesHeading" class="h1 mb-2">{{ $gameShortName }} Boost Services Pricing</h2>
          <p class="text-secondary mb-0">Choose a service, tune the details, and see the projected total before checkout.</p>
        </div>
      </div>
      @include('home.partials.services-tabs')

      <div class="tab-content">
        @foreach($serviceTabs as $serviceTab)
          @include('home.partials.services.'.$serviceTab['partial'], [
            'serviceTab' => $serviceTab,
            'serviceType' => $serviceTab['name'],
            'isActiveServiceTab' => $serviceTab['active'],
          ])
        @endforeach
      </div>
    </section>

    @include('home.partials.rank-picker-modal')

    @include('home.partials.budget-note')
    @include('home.partials.featured-boosters')
    @include('home.partials.how-it-works')
    <x-trust.review-section
      id="legacyHomeReviewsHeading"
      :reviews="$reviews ?? collect()"
      kicker="Reviews"
      :title="'Trusted by '.$gameShortName.' players'"
      description="Short proof from customers who care about communication, delivery speed, and safe order handling."
    />
    @include('home.partials.faq')
    <x-trust.live-chat-cta
      id="legacyHomeLiveChatCtaHeading"
      title="Need help before checkout?"
      body="Open live chat and we will help compare service type, delivery mode, and add-ons before you place an order."
    />
    @include('home.partials.latest-blogs')
  @endif
@endsection
