@extends('layouts.layout')

@php
  $reviewsPage = $pageContent ?? [];
@endphp

@section('title', 'VALORANT Boosting Reviews')

@section('content')
<div class="ggwp-page-shell ggwp-reviews-page">
  <header class="ggwp-public-hero ggwp-public-hero--compact">
    <div>
      <span class="ggwp-page-eyebrow">Customer proof</span>
      <h1 class="mb-2">{{ data_get($reviewsPage, 'hero.title', 'VALORANT Boosting Reviews') }}</h1>
      <p class="text-secondary mb-0">{{ data_get($reviewsPage, 'hero.description', 'Verified customer feedback, recent order highlights, and public proof from completed VALORANT boost orders.') }}</p>
    </div>
    <a class="btn btn-danger" href="{{ route('home') }}#services">Build a quote</a>
  </header>

  <section class="ggwp-trust-strip ggwp-trust-strip--tight" aria-label="Review page trust signals">
    <article class="ggwp-trust-strip__item">
      <span class="ggwp-trust-strip__label">Verified flow</span>
      <strong>Feedback from completed customer orders</strong>
    </article>
    <article class="ggwp-trust-strip__item">
      <span class="ggwp-trust-strip__label">Order context</span>
      <strong>Each review includes the service ordered</strong>
    </article>
  </section>

  <section class="row g-3">
    @forelse(($reviews ?? collect()) as $review)
      <article class="col-md-6 col-xl-4">
        <figure class="card app-card ggwp-panel-card h-100 mb-0">
          <div class="card-body d-flex flex-column gap-3">
            <span class="badge text-bg-secondary align-self-start">{{ $review->service }}</span>
            <blockquote class="mb-0 flex-grow-1">
              <p class="mb-0 ggwp-review-quote">&ldquo;{{ $review->quote }}&rdquo;</p>
            </blockquote>
            <figcaption>
              <div class="fw-semibold">{{ $review->author_name }}</div>
              <div class="small text-secondary">Verified GGWP-Boost customer review</div>
            </figcaption>
          </div>
        </figure>
      </article>
    @empty
      <div class="col-12">
        <div class="card app-card ggwp-panel-card">
          <div class="card-body text-center text-secondary">
            No reviews are available right now.
          </div>
        </div>
      </div>
    @endforelse
  </section>
</div>
@endsection
