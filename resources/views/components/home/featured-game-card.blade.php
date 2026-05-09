@props([
    'game',
])

@php
    $services = collect(data_get($game, 'mainServices', []))->filter()->take(3);
    $name = data_get($game, 'name', 'Featured game');
    $shortName = data_get($game, 'shortName', $name);
    $gameUrl = data_get($game, 'url', data_get($game, 'ctaUrl', route('checkout')));
    $categoryName = data_get($game, 'category.name', 'Competitive');
    $categoryUrl = data_get($game, 'category.url');
@endphp

<article class="ggwp-featured-game-card">
    <div class="ggwp-featured-game-card__media">
        @if(data_get($game, 'imageUrl'))
            <img
                src="{{ data_get($game, 'imageUrl') }}"
                alt="{{ data_get($game, 'imageAlt', $name . ' artwork') }}"
                width="640"
                height="360"
                loading="lazy"
                decoding="async"
            >
        @else
            <span aria-hidden="true">{{ data_get($game, 'initials', 'GG') }}</span>
        @endif
    </div>

    <div class="ggwp-featured-game-card__body">
        <div>
            @if($categoryUrl)
                <a class="ggwp-featured-game-card__category" href="{{ $categoryUrl }}">{{ $categoryName }}</a>
            @else
                <span class="ggwp-featured-game-card__category">{{ $categoryName }}</span>
            @endif
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

            <a
                class="btn btn-danger btn-sm"
                href="{{ $gameUrl }}"
                aria-label="View {{ $name }} boosting services"
                data-analytics-game-card
                data-analytics-context="featured_games"
                data-analytics-game-slug="{{ data_get($game, 'slug') }}"
                data-analytics-game-name="{{ $name }}"
            >
                View {{ $shortName }} services
            </a>
        </div>
    </div>
</article>
