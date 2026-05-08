@props([
    'items' => [],
])

@php
    $benefits = collect($items)->values();
@endphp

<section class="section-block ggwp-marketplace-section ggwp-why-choose" aria-labelledby="whyChooseHeading">
    <x-home.section-heading
        id="whyChooseHeading"
        kicker="Why Choose GGWPBoost"
        title="Built for safer orders and faster decisions"
        description="Clear service scope, direct support, and secure checkout help customers move from quote to delivery with less friction."
    />

    <div class="ggwp-trust-badge-strip ggwp-trust-badge-strip--embedded ggwp-why-choose__grid" aria-label="Marketplace trust badges" data-conversion-component="badge-strip">
        @foreach($benefits as $item)
            @php
                $title = (string) data_get($item, 'title', 'Marketplace benefit');
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
                :body="data_get($item, 'body', 'Helpful service coverage for competitive game orders.')"
            />
        @endforeach
    </div>
</section>
