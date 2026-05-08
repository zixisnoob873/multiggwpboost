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

  <x-trust.badge-strip
    class="ggwp-trust-strip--tight"
    :badges="[
      ['type' => 'verified-booster', 'title' => 'Verified flow', 'body' => 'Feedback from completed customer orders.'],
      ['type' => 'secure-payment', 'title' => 'Order context', 'body' => 'Each review includes the service ordered.'],
    ]"
    label="Review page trust signals"
  />

  <section class="row g-3">
    @forelse(($reviews ?? collect()) as $review)
      <article class="col-md-6 col-xl-4">
        <x-trust.review-card class="h-100 mb-0" :review="$review" />
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
