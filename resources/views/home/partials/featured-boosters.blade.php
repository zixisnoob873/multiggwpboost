<section id="featured-boosters" class="section-block ggwp-section-anchor ggwp-home-featured" aria-labelledby="featuredBoostersHeading">
  <div class="ggwp-home-section-header">
    <div>
      <span class="ggwp-home-section-kicker">Vetted Team</span>
      <h2 id="featuredBoostersHeading" class="h1 mb-2 ggwp-featured-heading">Featured VALORANT Boosters</h2>
      <p class="text-secondary mb-0">Trusted high-performance boosters vetted for safe VALORANT boosting, reliable communication, and verified delivery speed.</p>
    </div>
  </div>
  <div class="row g-3">
    @forelse(($featuredBoosters ?? collect()) as $booster)
      <div class="col-md-4">
        <article class="card app-card h-100 ggwp-featured-booster-card">
          <div class="card-body">
            <header class="ggwp-featured-booster-card__header">
              <h3 class="h4 mb-0 ggwp-featured-booster-name">{{ $booster->name }}</h3>
              @if($booster->is_verified)
                <span class="ggwp-featured-booster-verified">
                  <img
                    src="{{ asset('assets/verified_booster.png') }}"
                    alt=""
                    class="ggwp-featured-booster-badge"
                    aria-hidden="true"
                    loading="lazy"
                    decoding="async"
                  >
                  <span>Verified</span>
                </span>
              @endif
            </header>
            <dl class="ggwp-featured-booster-stats">
              <div>
                <dt>Region</dt>
                <dd>{{ $booster->region }}</dd>
              </div>
              <div>
                <dt>Platform</dt>
                <dd>{{ $booster->platform }}</dd>
              </div>
              <div>
                <dt>Success rate</dt>
                <dd>{{ number_format((float) $booster->success_rate, 1) }}%</dd>
              </div>
              <div>
                <dt>Active orders</dt>
                <dd>{{ $booster->active_orders }}</dd>
              </div>
            </dl>
          </div>
        </article>
      </div>
    @empty
      <div class="col-12">
        <div class="card app-card ggwp-home-empty-state">
          <div class="card-body text-center">
            <h3 class="h5 mb-0">No featured VALORANT boosters are published.</h3>
          </div>
        </div>
      </div>
    @endforelse
  </div>
</section>
