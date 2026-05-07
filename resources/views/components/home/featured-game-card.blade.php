@props([
    'game',
])

@php
    $services = collect(data_get($game, 'mainServices', []))->filter()->take(3);
    $name = data_get($game, 'name', 'Featured game');
    $shortName = data_get($game, 'shortName', $name);
    $ctaUrl = data_get($game, 'ctaUrl', route('checkout'));
@endphp

<article class="ggwp-featured-game-card">
    <div class="ggwp-featured-game-card__media">
        @if(data_get($game, 'imageUrl'))
            <img
                src="{{ data_get($game, 'imageUrl') }}"
                alt=""
                loading="lazy"
                decoding="async"
            >
        @else
            <span aria-hidden="true">{{ data_get($game, 'initials', 'GG') }}</span>
        @endif
    </div>

    <div class="ggwp-featured-game-card__body">
        <div>
            <span class="ggwp-featured-game-card__category">{{ data_get($game, 'category.name', 'Competitive') }}</span>
            <h3>{{ $name }}</h3>
        </div>

        @if($services->isNotEmpty())
            <ul class="ggwp-featured-game-card__services" aria-label="{{ $name }} main services">
                @foreach($services as $service)
                    <li>{{ $service }}</li>
                @endforeach
            </ul>
        @endif

        <div class="ggwp-featured-game-card__footer">
            <div class="ggwp-featured-game-card__price">
                <span>Starting at</span>
                <strong>{{ data_get($game, 'startingPriceLabel', 'Custom quote') }}</strong>
            </div>

            <a class="btn btn-danger btn-sm" href="{{ $ctaUrl }}" aria-label="Order {{ $name }} boosting">
                Order {{ $shortName }}
            </a>
        </div>
    </div>
</article>
