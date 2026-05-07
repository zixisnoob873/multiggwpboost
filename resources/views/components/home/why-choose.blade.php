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

    <div class="ggwp-why-choose__grid">
        @foreach($benefits as $item)
            <article class="ggwp-why-choose-card">
                <span class="ggwp-why-choose-card__mark" aria-hidden="true"></span>
                <h3>{{ data_get($item, 'title', 'Marketplace benefit') }}</h3>
                <p>{{ data_get($item, 'body', 'Helpful service coverage for competitive game orders.') }}</p>
            </article>
        @endforeach
    </div>
</section>
