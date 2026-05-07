@php
  $howItWorks = data_get($pageContent ?? [], 'how_it_works', []);
  $steps = collect(data_get($howItWorks, 'steps', []));
@endphp

<section class="section-block ggwp-home-steps" aria-labelledby="howItWorksHeading">
  <div class="ggwp-home-section-header">
    <div>
      <span class="ggwp-home-section-kicker">Simple Flow</span>
      <h2 id="howItWorksHeading" class="h1 mb-2">{{ data_get($howItWorks, 'title', 'How Your '.($gameShortName ?? 'VALORANT').' Boost Works') }}</h2>
      <p class="text-secondary mb-0">From quote to progress tracking, the core flow stays predictable.</p>
    </div>
  </div>
  <div class="row g-3 ggwp-home-steps__grid">
    @foreach($steps as $step)
      <div class="col-md-4">
        <article class="card app-card h-100 ggwp-home-step-card">
          <div class="card-body">
            <span class="ggwp-home-step-card__number" aria-hidden="true">{{ $loop->iteration }}</span>
            <h3 class="h4">{{ data_get($step, 'title') }}</h3>
            <p class="text-secondary mb-0">{{ data_get($step, 'body') }}</p>
          </div>
        </article>
      </div>
    @endforeach
  </div>
</section>
