@props([
    'game' => [],
    'services' => [],
])

@php
    $items = collect($services)->values();
    $gameName = data_get($game, 'name', 'this game');
    $gameShortName = data_get($game, 'shortName', $gameName);
@endphp

<section id="available-services" class="section-block ggwp-game-section ggwp-game-services" aria-labelledby="availableServicesHeading">
    <x-home.section-heading
        id="availableServicesHeading"
        :kicker="$gameShortName.' services'"
        title="Available Services"
        description="Open a service page to configure pricing, add-ons, delivery mode, and order scope before checkout."
    />

    @if($items->isNotEmpty())
        <div class="ggwp-game-services__grid">
            @foreach($items as $service)
                <x-game.service-card :service="$service" />
            @endforeach
        </div>
    @else
        <p class="ggwp-game-empty">No published services are available for {{ $gameName }} yet.</p>
    @endif
</section>
