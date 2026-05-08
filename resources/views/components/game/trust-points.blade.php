@props([
    'game' => [],
    'items' => [],
])

@php
    $points = collect($items)->values();
    $gameShortName = data_get($game, 'shortName', data_get($game, 'name', 'Game'));
@endphp

<section class="section-block ggwp-game-section ggwp-game-trust" aria-labelledby="gameTrustHeading">
    <x-home.section-heading
        id="gameTrustHeading"
        kicker="Why players choose GGWPBoost"
        :title="'Built for safe, fast, trackable '.$gameShortName.' orders'"
        description="Conversion matters, but so does confidence. Every order flow keeps safety, communication, and support close."
    />

    <div class="ggwp-trust-badge-strip ggwp-trust-badge-strip--embedded ggwp-game-trust__grid" aria-label="{{ $gameShortName }} trust badges" data-conversion-component="badge-strip">
        @foreach($points as $point)
            @php
                $title = (string) data_get($point, 'title', 'Trust point');
                $normalizedTitle = strtolower($title);
                $type = match (true) {
                    str_contains($normalizedTitle, 'vpn') => 'vpn',
                    str_contains($normalizedTitle, 'fast') || str_contains($normalizedTitle, 'delivery') => 'fast-delivery',
                    str_contains($normalizedTitle, 'payment') || str_contains($normalizedTitle, 'checkout') => 'secure-payment',
                    str_contains($normalizedTitle, 'support') => 'support',
                    str_contains($normalizedTitle, 'booster') => 'verified-booster',
                    default => 'secure-payment',
                };
            @endphp

            <x-trust.badge
                :type="$type"
                :title="$title"
                :body="data_get($point, 'body', '')"
            />
        @endforeach
    </div>
</section>
