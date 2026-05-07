@php
    $featuredGames = collect($featuredGames ?? []);
    $homepageFeaturedServices = collect($homepageFeaturedServices ?? []);
@endphp

@if($featuredGames->isNotEmpty() || $homepageFeaturedServices->isNotEmpty())
  <section class="section-block ggwp-marketplace-highlights" aria-labelledby="marketplaceHighlightsHeading">
    <div class="ggwp-home-section-header">
      <div>
        <span class="ggwp-home-section-kicker">Marketplace</span>
        <h2 id="marketplaceHighlightsHeading" class="h1 mb-2">Featured games and services</h2>
        <p class="text-secondary mb-0">Browse active marketplace options configured from the catalog.</p>
      </div>
    </div>

    @if($featuredGames->isNotEmpty())
      <div class="row g-3 mb-4" aria-label="Featured games">
        @foreach($featuredGames as $featuredGame)
          <div class="col-md-6 col-xl-3">
            <article class="card app-card h-100">
              <div class="card-body">
                <span class="ggwp-home-section-kicker">{{ data_get($featuredGame, 'category.name', 'Game') }}</span>
                <h3 class="h5 mt-2">{{ $featuredGame['name'] ?? 'Featured game' }}</h3>
                @if(! empty($featuredGame['description']))
                  <p class="text-secondary">{{ $featuredGame['description'] }}</p>
                @endif
                <a class="btn btn-outline-light btn-sm" href="{{ $featuredGame['url'] ?? route('home') }}">
                  View {{ $featuredGame['shortName'] ?? $featuredGame['name'] ?? 'game' }} services
                </a>
              </div>
            </article>
          </div>
        @endforeach
      </div>
    @endif

    @if($homepageFeaturedServices->isNotEmpty())
      <div class="row g-3" aria-label="Homepage featured services">
        @foreach($homepageFeaturedServices as $featuredService)
          <div class="col-md-6 col-xl-3">
            <article class="card app-card h-100">
              <div class="card-body">
                <span class="ggwp-home-section-kicker">{{ $featuredService['gameShortName'] ?? $featuredService['gameName'] ?? 'Service' }}</span>
                <h3 class="h5 mt-2">{{ $featuredService['name'] ?? 'Featured service' }}</h3>
                @if(! empty($featuredService['description']))
                  <p class="text-secondary">{{ $featuredService['description'] }}</p>
                @endif
                <a class="btn btn-danger btn-sm" href="{{ $featuredService['url'] ?? route('home') }}">
                  Open {{ $featuredService['name'] ?? 'service' }}
                </a>
              </div>
            </article>
          </div>
        @endforeach
      </div>
    @endif
  </section>
@endif
