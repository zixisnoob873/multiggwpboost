@extends('layouts.layout')

@php
    $rankOptions = $ggwpRankOptions ?? [];
    $rankOptionsWithRadiant = $ggwpRankOptionsWithRadiant ?? [];
    $regions = $ggwpRegions ?? [];
    $platforms = $ggwpPlatforms ?? [];
    $boostModeOptions = $ggwpBoostModeOptions ?? [];
    $averageRrOptionChoices = $ggwpAverageRrOptionChoices ?? [];
    $selfPlayBoostModeLabel = collect($boostModeOptions)->firstWhere('value', 'self_play')['label'] ?? 'Duo / Self-Play';
@endphp

@push('head')
    <meta name="cryptomus" content="fdcccf04" />
@endpush

@section('content')
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

    <section id="services" class="section-block ggwp-section-anchor ggwp-home-services" aria-labelledby="servicesHeading">
      <div class="ggwp-home-section-header">
        <div>
          <span class="ggwp-home-section-kicker">Live Pricing</span>
          <h2 id="servicesHeading" class="h1 mb-2">VALORANT Boost Services Pricing</h2>
          <p class="text-secondary mb-0">Choose a service, tune the details, and see the projected total before checkout.</p>
        </div>
      </div>
      @include('home.partials.services-tabs')

      <div class="tab-content">
        @include('home.partials.services.boosting')
        @include('home.partials.services.placement')
        @include('home.partials.services.radiant')
        @include('home.partials.services.ranked')
      </div>
    </section>

    @include('home.partials.rank-picker-modal')

    @include('home.partials.budget-note')
    @include('home.partials.featured-boosters')
    @include('home.partials.how-it-works')
    @include('home.partials.faq')
    @include('home.partials.latest-blogs')
@endsection
