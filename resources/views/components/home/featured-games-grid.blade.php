@props([
    'games' => [],
])

@php
    $items = collect($games)->values();
@endphp

<section id="featured-games" class="section-block ggwp-marketplace-section ggwp-featured-games" aria-labelledby="featuredGamesHeading">
    <x-home.section-heading
        id="featuredGamesHeading"
        kicker="Featured Games"
        title="Featured games and services"
        description="Pick your title, compare active service pages, and configure the exact order before checkout."
    >
        <a class="btn btn-outline-light btn-sm" href="#popular-services">Compare services</a>
    </x-home.section-heading>

    <div class="ggwp-featured-games__grid">
        @forelse($items as $game)
            <x-home.featured-game-card :game="$game" />
        @empty
            <div class="ggwp-marketplace-empty">
                Featured games will appear here shortly.
            </div>
        @endforelse
    </div>
</section>
