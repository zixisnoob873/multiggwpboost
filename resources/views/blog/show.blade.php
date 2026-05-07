@extends('layouts.layout')

@php
    $faqItems = $article->faqItems();
    $ctaUrl = $article->effectiveCtaUrl();
    $ctaLabel = $article->effectiveCtaLabel();
@endphp

@section('content')
<div class="ggwp-page-shell ggwp-page-shell--wide ggwp-blog-shell">
    <article class="ggwp-blog-article">
        <header class="card app-card ggwp-panel-card ggwp-blog-article__hero">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a class="btn btn-outline-light btn-sm" href="{{ route('blog.index') }}">Back to blog</a>
                    <a class="btn btn-outline-light btn-sm" href="/#servicesTab">Explore VALORANT Boosts</a>
                </div>

                <div class="ggwp-blog-article__meta mb-3">
                    <span>{{ $article->published_at?->format('M j, Y') ?? 'Unpublished' }}</span>
                    <span>{{ $article->readingTimeInMinutes() }} min read</span>
                    @if($article->updated_at && $article->updated_at->ne($article->published_at))
                        <span>Updated {{ $article->updated_at->format('M j, Y') }}</span>
                    @endif
                </div>

                <h1 class="ggwp-blog-article__title mb-3">{{ $article->title }}</h1>
                <p class="ggwp-blog-article__intro mb-0">{{ $article->intro }}</p>
            </div>
        </header>

        <div class="row g-4 mt-1 align-items-start">
            <div class="col-xl-8">
                <section class="card app-card ggwp-panel-card">
                    <div class="card-body">
                        <div class="ggwp-blog-prose">
                            {!! $article->renderedBody() !!}
                        </div>
                    </div>
                </section>

                @if($faqItems !== [])
                    <section class="card app-card ggwp-panel-card mt-4">
                        <div class="card-body">
                            <h2 class="ggwp-blog-section-title mb-3">FAQ</h2>
                            <div class="accordion ggwp-accordion" id="blogFaqAccordion">
                                @foreach($faqItems as $faqItem)
                                    <article class="accordion-item">
                                        <h3 class="accordion-header" id="blog-faq-heading-{{ $loop->index }}">
                                            <button
                                                class="accordion-button {{ $loop->first ? '' : 'collapsed' }}"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#blog-faq-collapse-{{ $loop->index }}"
                                                aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                                                aria-controls="blog-faq-collapse-{{ $loop->index }}"
                                            >
                                                {{ $faqItem['question'] }}
                                            </button>
                                        </h3>
                                        <div
                                            id="blog-faq-collapse-{{ $loop->index }}"
                                            class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}"
                                            aria-labelledby="blog-faq-heading-{{ $loop->index }}"
                                            data-bs-parent="#blogFaqAccordion"
                                        >
                                            <div class="accordion-body text-secondary">{{ $faqItem['answer'] }}</div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif
            </div>

            <aside class="col-xl-4" aria-label="Article navigation and related resources">
                <div class="ggwp-blog-sidebar">
                    <nav class="card app-card ggwp-panel-card" aria-labelledby="articleQuickActionsHeading">
                        <div class="card-body">
                            <h2 id="articleQuickActionsHeading" class="h5 mb-2">Quick actions</h2>
                            <p class="text-secondary mb-3">Use the service hub when you want to compare VALORANT boost types, Duo / Self-Play modes, and add-on paths without leaving the article flow.</p>
                            <div class="d-grid gap-2">
                                @if($ctaUrl && $ctaLabel)
                                    <a class="btn btn-danger" href="{{ $ctaUrl }}">{{ $ctaLabel }}</a>
                                @endif
                                <a class="btn btn-outline-light" href="{{ route('faq') }}">Read FAQ</a>
                                <a class="btn btn-outline-light" href="{{ route('contact') }}">Contact Support</a>
                            </div>
                        </div>
                    </nav>

                    <nav class="card app-card ggwp-panel-card" aria-labelledby="articleBoostLinksHeading">
                        <div class="card-body">
                            <h2 id="articleBoostLinksHeading" class="h5 mb-2">Explore VALORANT boosts</h2>
                            <p class="text-secondary mb-3">Jump back into the main service hub whenever you want to compare rank boosting, placements, ranked wins, and Radiant options side by side.</p>
                            <div class="d-grid gap-2">
                                <a class="btn btn-outline-light" href="/#servicesTab">All VALORANT boosts</a>
                                <a class="btn btn-outline-light" href="{{ route('home') }}#tab-boosting">Rank Boosting</a>
                                <a class="btn btn-outline-light" href="{{ route('home') }}#tab-placement">Placement Matches</a>
                                <a class="btn btn-outline-light" href="{{ route('home') }}#tab-ranked">Ranked Wins</a>
                            </div>
                        </div>
                    </nav>

                    @if($relatedArticles->isNotEmpty())
                        <section class="card app-card ggwp-panel-card">
                            <div class="card-body">
                                <h2 class="h5 mb-3">Related articles</h2>
                                <nav class="ggwp-blog-related" aria-label="Related articles">
                                    @foreach($relatedArticles as $relatedArticle)
                                        <a class="ggwp-blog-related__item" href="{{ route('blog.show', ['slug' => $relatedArticle->slug]) }}">
                                            <span class="ggwp-blog-related__title">{{ $relatedArticle->title }}</span>
                                            <span class="ggwp-blog-related__meta">{{ $relatedArticle->published_at?->format('M j, Y') }} | {{ $relatedArticle->readingTimeInMinutes() }} min</span>
                                        </a>
                                    @endforeach
                                </nav>
                            </div>
                        </section>
                    @endif
                </div>
            </aside>
        </div>
    </article>
</div>
@endsection
