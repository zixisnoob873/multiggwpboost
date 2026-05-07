@php
    $latestBlogArticles = ($latestBlogArticles ?? collect())->values();
    $latestBlogsContent = data_get($pageContent ?? [], 'latest_blogs', []);

    $slides = collect();

    if ($latestBlogArticles->isNotEmpty()) {
        if ($latestBlogArticles->count() <= 3) {
            $slides->push($latestBlogArticles);
        } else {
            foreach (range(0, $latestBlogArticles->count() - 3) as $startIndex) {
                $slides->push($latestBlogArticles->slice($startIndex, 3)->values());
            }
        }
    }
@endphp

@if($latestBlogArticles->isNotEmpty())
    <section id="home-latest-blogs" class="section-block ggwp-section-anchor ggwp-home-blog-section" aria-labelledby="latestBlogsHeading">
        <header class="ggwp-home-blog-section__header">
            <div>
                <span class="ggwp-home-section-kicker">Guides</span>
                <h2 id="latestBlogsHeading" class="h1 mb-2">{{ data_get($latestBlogsContent, 'title', 'VALORANT Boosting Guides') }}</h2>
                <p class="text-secondary mb-0">{{ data_get($latestBlogsContent, 'description', 'Fresh guides on VALORANT rank boosting, Duo / Self-Play choices, pricing factors, safety, and smarter ways to climb.') }}</p>
            </div>
            <a class="btn btn-outline-light" href="{{ route('blog.index') }}">{{ data_get($latestBlogsContent, 'button_label', 'Read VALORANT Guides') }}</a>
        </header>

        <div
            id="homeLatestBlogsCarousel"
            class="carousel slide ggwp-blog-carousel"
            data-bs-ride="carousel"
            data-bs-interval="5200"
            data-bs-pause="hover"
            data-bs-touch="true"
            data-card-selector=".ggwp-home-blog-card"
            aria-label="Latest VALORANT guides"
        >
            <div class="carousel-inner">
                @foreach($slides as $slide)
                    <div class="carousel-item {{ $loop->first ? 'active' : '' }}">
                        <div class="row g-3 align-items-stretch justify-content-center">
                            @foreach($slide as $article)
                                <div class="col-12 col-md-6 col-xl-4">
                                    <article class="card app-card ggwp-panel-card h-100 ggwp-blog-card ggwp-home-blog-card">
                                        <div class="card-body position-relative d-flex flex-column">
                                            <div class="ggwp-blog-card__meta">
                                                <span class="ggwp-blog-card__meta-pill">{{ $article->published_at?->format('M j, Y') }}</span>
                                                <span class="ggwp-blog-card__meta-pill">{{ $article->readingTimeInMinutes() }} min read</span>
                                            </div>

                                            <h3 class="ggwp-blog-card__title ggwp-home-blog-card__title">
                                                {{ $article->title }}
                                            </h3>

                                            <p class="text-secondary flex-grow-1 mb-3">{{ $article->excerpt }}</p>

                                            <div class="ggwp-home-blog-card__footer mt-auto pt-2">
                                                <a
                                                    class="ggwp-home-blog-card__link stretched-link"
                                                    href="{{ route('blog.show', ['slug' => $article->slug]) }}"
                                                    aria-label="Read article: {{ $article->title }}"
                                                >Read Article</a>
                                            </div>
                                        </div>
                                    </article>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            @if($slides->count() > 1)
                <div class="ggwp-home-blog-nav" aria-label="Latest blog carousel controls">
                    <button class="carousel-control-prev ggwp-home-blog-control" type="button" data-bs-target="#homeLatestBlogsCarousel" data-bs-slide="prev" aria-label="Previous blog group">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    </button>

                    <div class="carousel-indicators ggwp-home-blog-indicators">
                        @foreach($slides as $slide)
                            <button
                                type="button"
                                data-bs-target="#homeLatestBlogsCarousel"
                                data-bs-slide-to="{{ $loop->index }}"
                                class="{{ $loop->first ? 'active' : '' }}"
                                aria-current="{{ $loop->first ? 'true' : 'false' }}"
                                aria-label="Show latest blog set {{ $loop->iteration }}"
                            ></button>
                        @endforeach
                    </div>

                    <button class="carousel-control-next ggwp-home-blog-control" type="button" data-bs-target="#homeLatestBlogsCarousel" data-bs-slide="next" aria-label="Next blog group">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    </button>
                </div>
            @endif
        </div>
    </section>
@endif
