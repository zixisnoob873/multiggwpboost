@extends('layouts.layout')

@php
    $faqPage = $pageContent ?? [];
@endphp

@section('title', 'VALORANT Boosting FAQ')

@section('content')
<div class="ggwp-page-shell ggwp-faq-page">
    <section class="row g-3 align-items-stretch mb-3 ggwp-faq-hero">
        <header class="col-lg-8">
            <div class="card app-card h-100">
                <div class="card-body">
                    <span class="ggwp-page-eyebrow">{{ data_get($faqPage, 'hero.eyebrow', 'Support Center') }}</span>
                    <h1 class="display-6 fw-semibold mt-2 mb-3">{{ data_get($faqPage, 'hero.headline', 'VALORANT Boosting FAQ') }}</h1>
                    <p class="text-secondary mb-0">
                        {{ data_get($faqPage, 'hero.description', 'Everything customers usually ask before ordering a VALORANT boost, from safety and speed to Duo / Self-Play, pricing, and support.') }}
                    </p>
                </div>
            </div>
        </header>
        <aside class="col-lg-4" aria-labelledby="faqSupportHeading">
            <div class="card app-card h-100">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <h2 id="faqSupportHeading" class="h4 mb-2">{{ data_get($faqPage, 'sidebar.title', 'Need a faster answer?') }}</h2>
                        <p class="text-secondary mb-0">
                            {{ data_get($faqPage, 'sidebar.description', 'Reach out for help with VALORANT boost pricing, account safety, Duo / Self-Play orders, or custom requests.') }}
                        </p>
                    </div>
                    <div class="d-grid gap-2 mt-3">
                        <a class="btn btn-danger" href="{{ data_get($faqPage, 'sidebar.primary_cta_url', route('contact')) }}">{{ data_get($faqPage, 'sidebar.primary_cta_label', 'Contact Support') }}</a>
                        <a class="btn btn-outline-light" href="{{ data_get($faqPage, 'sidebar.secondary_cta_url', route('game.services.show', ['game' => 'valorant', 'service' => 'rank-boosting'])) }}">{{ data_get($faqPage, 'sidebar.secondary_cta_label', 'Start VALORANT Boost') }}</a>
                    </div>
                </div>
            </div>
        </aside>
    </section>

    <section class="card app-card ggwp-panel-card" aria-labelledby="faqListingHeading">
        <div class="card-body">
            <header class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                <div>
                    <h2 id="faqListingHeading" class="h3 mb-1">{{ data_get($faqPage, 'listing.title', 'Common Questions') }}</h2>
                    <p class="text-secondary mb-0">{{ data_get($faqPage, 'listing.description', 'Quick answers about safe VALORANT boosting, order flow, payment, and support.') }}</p>
                </div>
                <div class="text-secondary small">
                    {{ ($faqs ?? collect())->count() }} {{ ($faqs ?? collect())->count() === 1 ? 'question' : 'questions' }}
                </div>
            </header>

            @if(($faqs ?? collect())->isNotEmpty())
                <x-trust.faq-accordion
                    id="faqAccordion"
                    heading-id="faqListingHeading"
                    class="ggwp-trust-section--embedded"
                    :faqs="$faqs"
                    kicker=""
                    title=""
                    description=""
                />
            @else
                <div class="rounded-4 border border-secondary-subtle p-3 text-center text-secondary">
                    No FAQs are available right now.
                </div>
            @endif
        </div>
    </section>
</div>
@endsection
