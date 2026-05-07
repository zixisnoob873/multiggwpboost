@php
    $hero = data_get($pageContent ?? [], 'hero', []);
    $trustBullets = collect(data_get($hero, 'trust_bullets', []))
        ->pluck('text')
        ->filter();
    $promotions = collect($promotions ?? [])->values();
    $viewer = auth()->user();
    $viewerRole = \App\Models\User::normalizeRole($viewer?->role);
    $primaryCtaUrl = ($viewerRole === \App\Models\User::ROLE_BOOSTER)
        ? route('booster-dashboard')
        : data_get($hero, 'primary_cta_url', '/#servicesTab');
    $primaryCtaLabel = ($viewerRole === \App\Models\User::ROLE_BOOSTER)
        ? 'Open Booster Dashboard'
        : data_get($hero, 'primary_cta_label', 'Start Boost');
@endphp

<section class="row g-3 align-items-stretch mb-4 hero-wrap ggwp-home-hero" aria-labelledby="homeHeroHeading">
  <header class="col-lg-7">
    <div class="hero-copy">
      <h1 id="homeHeroHeading" class="display-3 fw-semibold mb-2">{{ data_get($hero, 'headline', 'Fast, Safe VALORANT Rank Boosting Built Around Your Goal.') }}</h1>
      <p class="text-secondary mb-3">{{ data_get($hero, 'description', 'Configure a VALORANT boost with Solo or Duo / Self-Play options, fair pricing, verified boosters, and live order tracking from start to finish.') }}</p>
      <div class="d-flex flex-wrap gap-2 mb-3 ggwp-home-hero__actions" aria-label="Primary actions">
        <a class="btn btn-danger ggwp-premium-cta" href="{{ $primaryCtaUrl }}">{{ $primaryCtaLabel }}</a>
        <a class="btn btn-outline-light" href="{{ data_get($hero, 'secondary_cta_url', route('become-booster')) }}">{{ data_get($hero, 'secondary_cta_label', 'Become a Booster') }}</a>
      </div>
      <div class="hero-trust-list" role="list" aria-label="Service highlights">
        @foreach($trustBullets as $bullet)
          <span role="listitem">{{ $bullet }}</span>
        @endforeach
      </div>
    </div>
  </header>
  <aside class="col-lg-5" aria-label="Current promotions and service highlights">
    <div class="card app-card h-100 hero-trust-card">
      <div class="card-body">
        @if($promotions->isNotEmpty())
          <div id="heroPromoCarousel" class="carousel slide hero-promo-slider" data-bs-ride="carousel" data-bs-interval="4200" data-bs-touch="true" aria-label="Current promotions">
            @if($promotions->count() > 1)
              <button
                type="button"
                class="hero-promo-toggle"
                data-hero-promo-toggle
                aria-pressed="false"
                aria-label="Pause promotion carousel"
              >
                Pause
              </button>
            @endif
            <div class="carousel-indicators hero-promo-indicators">
              @foreach($promotions as $promotion)
                <button
                  type="button"
                  data-bs-target="#heroPromoCarousel"
                  data-bs-slide-to="{{ $loop->index }}"
                  class="{{ $loop->first ? 'active' : '' }}"
                  aria-current="{{ $loop->first ? 'true' : 'false' }}"
                  aria-label="Show promotion {{ $loop->iteration }}"
                ></button>
              @endforeach
            </div>
            <div class="carousel-inner">
              @foreach($promotions as $promotion)
                <div class="carousel-item{{ $loop->first ? ' active' : '' }}">
                  <article class="hero-promo-card" aria-label="{{ $promotion->title ?: 'VALORANT boost promotion' }}">
                    <div class="hero-promo-media">
                      @if(filled($promotion->image_path))
                        <img
                          src="{{ $promotion->imageUrl() }}"
                          alt="{{ $promotion->title ?: 'Promotion artwork' }}"
                          class="hero-promo-image"
                          loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                          decoding="async"
                        >
                      @else
                        <div class="hero-promo-image hero-promo-image--fallback" aria-hidden="true"></div>
                      @endif
                    </div>
                    <div class="hero-promo-overlay">
                      <span class="hero-promo-badge">Promotions & Deals</span>
                      <h2 class="hero-promo-title">{{ $promotion->title ?: 'Fast VALORANT Boost Deals' }}</h2>
                      <p class="hero-promo-copy mb-0">{{ $promotion->description ?: 'Flexible Solo and Duo / Self-Play options, safe handling, and premium support.' }}</p>
                      @if(filled($promotion->button_link))
                        <a class="btn btn-danger hero-promo-link" href="{{ $promotion->button_link }}">
                          {{ $promotion->button_text ?: 'Learn More' }}
                        </a>
                      @endif
                    </div>
                  </article>
                </div>
              @endforeach
            </div>
          </div>
        @else
          <div class="hero-promo-empty hero-promo-empty--planner">
            <span class="hero-promo-badge">Promotions & Deals</span>
            <h2 class="hero-promo-title">Fast VALORANT Boosting, Ready When You Are</h2>
            <p class="hero-promo-copy mb-0">Choose your service, set your rank goal, and track every update from your dashboard.</p>
            <a class="btn btn-danger hero-promo-link" href="#servicesTab">Start VALORANT Boost</a>
          </div>
        @endif
      </div>
    </div>
  </aside>
</section>
