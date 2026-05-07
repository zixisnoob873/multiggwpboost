@extends('layouts.layout')

@php
    $hero = data_get($pageContent ?? [], 'hero', []);
    $listing = data_get($pageContent ?? [], 'listing', []);
@endphp

@section('content')
<div class="ggwp-page-shell ggwp-page-shell--wide ggwp-blog-shell">
    <header class="ggwp-blog-hero ggwp-public-hero card app-card ggwp-panel-card">
        <div class="card-body">
            <div class="row g-4 align-items-center">
                <div class="col-lg-8">
                    <span class="ggwp-blog-eyebrow">{{ data_get($hero, 'eyebrow', 'VALORANT BOOSTING BLOG') }}</span>
                    <h1 class="ggwp-blog-hero__title mb-3">{{ data_get($hero, 'headline', 'VALORANT Boosting Guides, Safety Advice, and Rank-Up Strategy') }}</h1>
                    <p class="ggwp-blog-hero__copy mb-0">
                        {{ data_get($hero, 'description', 'Clear articles on VALORANT rank boosting, Duo / Self-Play choices, placement strategy, pricing factors, and realistic ways to climb faster without wasting time.') }}
                    </p>
                </div>
                <div class="col-lg-4">
                    <aside class="ggwp-blog-hero__aside" aria-labelledby="blogHeroAsideHeading">
                        <h2 id="blogHeroAsideHeading" class="h5 mb-2">{{ data_get($hero, 'aside_title', 'Compare VALORANT boost options') }}</h2>
                        <p class="text-secondary mb-3">
                            {{ data_get($hero, 'aside_description', 'Jump to the service hub to compare rank boosting, placements, ranked wins, Radiant paths, and Duo / Self-Play modes.') }}
                        </p>
                        <a class="btn btn-danger" href="{{ data_get($hero, 'cta_url', '/#servicesTab') }}">{{ data_get($hero, 'cta_label', 'Explore VALORANT Boosts') }}</a>
                    </aside>
                </div>
            </div>
        </div>
    </header>

    <section class="mt-4" aria-labelledby="blogListingHeading">
        <header class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
            <div>
                <h2 id="blogListingHeading" class="ggwp-blog-section-title mb-1">{{ data_get($listing, 'title', 'Latest VALORANT Boosting Articles') }}</h2>
                <p class="text-secondary mb-0">{{ $articles->total() }} {{ $articles->total() === 1 ? 'article' : 'articles' }} available.</p>
            </div>
            <div class="text-secondary small">{{ data_get($listing, 'description', 'Practical reading for safer orders, clearer pricing, and better VALORANT boost decisions.') }}</div>
        </header>

        @if($articles->count() > 0)
            <div class="row g-3">
                @foreach($articles as $article)
                    <div class="col-12 col-md-6 col-xl-4">
                        <article class="card app-card ggwp-panel-card h-100 ggwp-blog-card">
                            <div class="card-body d-flex flex-column">
                                <div class="ggwp-blog-card__meta">
                                    <span>{{ $article->published_at?->format('M j, Y') ?? 'Draft' }}</span>
                                    <span>{{ $article->readingTimeInMinutes() }} min read</span>
                                </div>
                                <h3 class="ggwp-blog-card__title">
                                    <a href="{{ route('blog.show', ['slug' => $article->slug]) }}">{{ $article->title }}</a>
                                </h3>
                                <p class="text-secondary flex-grow-1 mb-3">{{ $article->excerpt }}</p>
                                <div class="d-flex flex-wrap gap-2 mt-auto">
                                    <a class="btn btn-danger btn-sm" href="{{ route('blog.show', ['slug' => $article->slug]) }}">Read article</a>
                                </div>
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $articles->withQueryString()->links('pagination::bootstrap-5') }}
            </div>
        @else
            <div class="card app-card ggwp-panel-card">
                <div class="card-body text-center py-5">
                    <h2 class="h4 mb-2">No articles available right now</h2>
                    <p class="text-secondary mb-0">Check back soon for VALORANT boosting guides, pricing notes, and service updates.</p>
                </div>
            </div>
        @endif
    </section>
</div>
@endsection
