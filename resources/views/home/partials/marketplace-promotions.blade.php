@php
    $promotions = collect($promotions ?? [])->values();
@endphp

@if($promotions->isNotEmpty())
    <section class="section-block ggwp-marketplace-section ggwp-home-promotions" aria-labelledby="homePromotionsHeading">
        <x-home.section-heading
            id="homePromotionsHeading"
            kicker="Promotions"
            title="Current boosting deals"
            description="Active homepage offers from the GGWPBoost team."
        />

        <div class="ggwp-home-promotions__grid">
            @foreach($promotions as $promotion)
                @php
                    $promotionImageUrl = $promotion->imageUrl();
                @endphp
                <article class="ggwp-home-promotion" aria-labelledby="homePromotion{{ $promotion->id }}Title">
                    @if(filled($promotionImageUrl))
                        <img
                            class="ggwp-home-promotion__image"
                            src="{{ $promotionImageUrl }}"
                            alt="{{ $promotion->title ?: 'Promotion artwork' }}"
                            loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                            decoding="async"
                        >
                    @endif

                    <div class="ggwp-home-promotion__body">
                        <h3 id="homePromotion{{ $promotion->id }}Title">{{ $promotion->title }}</h3>
                        <p>{{ $promotion->description }}</p>

                        @if(filled($promotion->button_link))
                            <a class="btn btn-danger btn-sm" href="{{ $promotion->button_link }}">
                                {{ $promotion->button_text ?: 'Learn More' }}
                            </a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif
