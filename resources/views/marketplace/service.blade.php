@extends('layouts.layout')

@php
    $rankOptions = $ggwpRankOptions ?? [];
    $rankOptionsWithRadiant = $ggwpRankOptionsWithRadiant ?? [];
    $regions = $ggwpRegions ?? [];
    $platforms = $ggwpPlatforms ?? [];
    $boostModeOptions = $ggwpBoostModeOptions ?? [];
    $averageRrOptionChoices = $ggwpAverageRrOptionChoices ?? [];
    $selfPlayBoostModeLabel = collect($boostModeOptions)->firstWhere('value', 'self_play')['label'] ?? 'Duo / Self-Play';
    $gameShortName = data_get($activeGame ?? [], 'shortName', data_get($activeGame ?? [], 'name', 'VALORANT'));
    $gameName = data_get($activeGame ?? [], 'name', $gameShortName);
    $serviceName = data_get($activeService ?? [], 'name', 'Boosting Service');
    $serviceDescription = data_get($activeService ?? [], 'description')
        ?: "Configure {$gameShortName} {$serviceName}, review the details, and continue to secure checkout.";
    $serviceTabs = $serviceTabs ?? [];
    $relatedServices = collect($relatedServices ?? []);
@endphp

@section('content')
  <header class="ggwp-page-shell ggwp-marketplace-service-hero">
    <section class="ggwp-legal-hero app-card" aria-labelledby="servicePageTitle">
      <span class="ggwp-page-eyebrow">{{ $gameShortName }} service</span>
      <h1 id="servicePageTitle">{{ $gameShortName }} {{ $serviceName }}</h1>
      <p>{{ $serviceDescription }}</p>
      <div class="d-flex flex-column flex-sm-row gap-2 mt-4">
        <a class="btn btn-danger" href="{{ route('checkout', ['game' => data_get($activeGame, 'slug', 'valorant')]) }}">
          Continue to checkout
        </a>
        <a class="btn btn-outline-light" href="{{ route('games.show', ['game' => data_get($activeGame, 'slug', 'valorant')]) }}#services">
          Compare {{ $gameShortName }} services
        </a>
      </div>
    </section>
  </header>

  @if(! empty($serviceCalculatorTab) && ! empty($serviceTabs))
    <section id="services" class="section-block ggwp-section-anchor ggwp-home-services" aria-labelledby="servicePricingHeading">
      <div class="ggwp-home-section-header">
        <div>
          <span class="ggwp-home-section-kicker">Live Pricing</span>
          <h2 id="servicePricingHeading" class="h1 mb-2">{{ $serviceName }} pricing</h2>
          <p class="text-secondary mb-0">Tune the order details and see the projected total before checkout.</p>
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
  @else
    <section class="section-block" aria-labelledby="serviceRequestHeading">
      <div class="card app-card">
        <div class="card-body p-4 p-xl-5">
          <span class="ggwp-home-section-kicker">Custom request</span>
          <h2 id="serviceRequestHeading" class="h3 mt-2">Start {{ $serviceName }} for {{ $gameShortName }}</h2>
          <p class="text-secondary">This service is active in the marketplace. Continue to checkout to send the configured request to the team.</p>
          <a class="btn btn-danger" href="{{ route('checkout', ['game' => data_get($activeGame, 'slug', 'valorant')]) }}">
            Start {{ $serviceName }}
          </a>
        </div>
      </div>
    </section>
  @endif

  @if($relatedServices->isNotEmpty())
    <section class="section-block" aria-labelledby="relatedServicesHeading">
      <div class="ggwp-home-section-header">
        <div>
          <span class="ggwp-home-section-kicker">Related</span>
          <h2 id="relatedServicesHeading" class="h1 mb-2">Related {{ $gameShortName }} services</h2>
        </div>
      </div>
      <div class="row g-3">
        @foreach($relatedServices as $relatedService)
          <div class="col-md-6 col-xl-3">
            <article class="card app-card h-100">
              <div class="card-body">
                <span class="ggwp-home-section-kicker">{{ $relatedService['gameShortName'] ?? $gameShortName }}</span>
                <h3 class="h5 mt-2">{{ $relatedService['name'] ?? 'Service' }}</h3>
                @if(! empty($relatedService['description']))
                  <p class="text-secondary">{{ $relatedService['description'] }}</p>
                @endif
                <a class="btn btn-outline-light btn-sm" href="{{ $relatedService['url'] ?? route('games.show', ['game' => data_get($activeGame, 'slug', 'valorant')]) }}">
                  View {{ $relatedService['name'] ?? 'service' }}
                </a>
              </div>
            </article>
          </div>
        @endforeach
      </div>
    </section>
  @endif
@endsection
