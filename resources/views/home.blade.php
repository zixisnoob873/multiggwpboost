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

    <x-home.featured-games-grid :games="$featuredGames ?? []" />
    <x-home.popular-services :services="$popularServices ?? []" />
    <x-home.why-choose :items="$whyChooseItems ?? []" />
    <x-home.review-grid :reviews="$reviews ?? collect()" />
    <x-home.faq-list :faqs="$marketplaceFaqs ?? []" />
  @else
    @include('home.partials.hero')

    <section class="ggwp-trust-strip section-block" aria-label="GGWP Boost service guarantees">
      <article class="ggwp-trust-strip__item">
        <span class="ggwp-trust-strip__label">Order workspace</span>
        <strong>Live chat, progress, and status in one place</strong>
      </article>
      <article class="ggwp-trust-strip__item">
        <span class="ggwp-trust-strip__label">Transparent quote</span>
        <strong>Rank, region, platform, mode, and add-ons priced before checkout</strong>
      </article>
      <article class="ggwp-trust-strip__item">
        <span class="ggwp-trust-strip__label">Vetted boosters</span>
        <strong>Clear assignment, handoff, and completion proof flows</strong>
      </article>
    </section>

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
    @include('home.partials.faq')
    @include('home.partials.latest-blogs')
  @endif
@endsection
