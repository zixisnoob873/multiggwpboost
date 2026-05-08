@props([
    'game' => [],
    'services' => [],
])

@php
    $items = collect($services)->values();
    $gameShortName = data_get($game, 'shortName', data_get($game, 'name', 'Game'));
@endphp

<section class="section-block ggwp-game-section ggwp-game-related" aria-labelledby="relatedServicesHeading">
    <x-home.section-heading
        id="relatedServicesHeading"
        kicker="Related services"
        title="More ways to get competitive goals handled"
        :description="'Explore similar order types across other supported titles when your next goal is outside '.$gameShortName.'.'"
    />

    @if($items->isNotEmpty())
        <div class="ggwp-game-related__grid">
            @foreach($items as $service)
                <article class="ggwp-game-related-card">
                    <span class="ggwp-game-kicker">{{ data_get($service, 'gameShortName', 'Game') }}</span>
                    <h3>{{ data_get($service, 'name', 'Boosting Service') }}</h3>
                    <p>{{ data_get($service, 'description', 'View service details and checkout options.') }}</p>
                    <a class="btn btn-outline-light btn-sm" href="{{ data_get($service, 'url', route('checkout')) }}">
                        View service
                    </a>
                </article>
            @endforeach
        </div>
    @else
        <p class="ggwp-game-empty">Related marketplace services will appear here as the catalog expands.</p>
    @endif
</section>
