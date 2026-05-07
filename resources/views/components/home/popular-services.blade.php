@props([
    'services' => [],
])

@php
    $items = collect($services)->values();
@endphp

<section id="popular-services" class="section-block ggwp-marketplace-section ggwp-popular-services" aria-labelledby="popularServicesHeading">
    <x-home.section-heading
        id="popularServicesHeading"
        kicker="Popular Services"
        title="The fastest paths from stuck to climbing"
        description="Conversion-friendly, repeatable service paths for ranked ladders, seasonal progress, unlocks, coaching, and account goals."
    />

    <div class="ggwp-popular-services__grid">
        @forelse($items as $service)
            <x-home.popular-service-card :service="$service" />
        @empty
            <div class="ggwp-marketplace-empty">
                Popular services will appear here shortly.
            </div>
        @endforelse
    </div>
</section>
