@php
    $legalHero = data_get($pageContent ?? [], 'hero', []);
    $legalSections = collect(data_get($pageContent ?? [], 'sections', []));
@endphp

<div class="ggwp-page-shell ggwp-legal-page">
    <header class="ggwp-public-hero ggwp-public-hero--compact">
        <div>
            <span class="ggwp-page-eyebrow">GGWP Boost policy</span>
            <h1 class="mb-2">{{ data_get($legalHero, 'title') }}</h1>

            @if(filled(data_get($legalHero, 'intro')))
                <p class="text-secondary mb-0">
                    {{ data_get($legalHero, 'intro') }}
                </p>
            @endif
        </div>
    </header>

    <div class="row g-3 align-items-start">
        <aside class="col-lg-4">
            <section class="card app-card ggwp-panel-card ggwp-legal-nav-card">
                <div class="card-body">
                    <h2 class="h5 mb-3">Policy center</h2>
                    <nav class="ggwp-legal-nav" aria-label="Policy pages">
                        <a class="{{ request()->routeIs('terms-and-conditions') ? 'is-active' : '' }}" href="{{ route('terms-and-conditions') }}">Terms</a>
                        <a class="{{ request()->routeIs('privacy-policy') ? 'is-active' : '' }}" href="{{ route('privacy-policy') }}">Privacy policy</a>
                        <a class="{{ request()->routeIs('refund-policy') ? 'is-active' : '' }}" href="{{ route('refund-policy') }}">Refund policy</a>
                        <a class="{{ request()->routeIs('code-of-ethics') ? 'is-active' : '' }}" href="{{ route('code-of-ethics') }}">Code of ethics</a>
                    </nav>
                </div>
            </section>
        </aside>

        <section class="col-lg-8">
            <article class="card app-card ggwp-panel-card ggwp-legal-copy-card">
                <div class="card-body">

            @foreach($legalSections as $section)
                @php
                    $bullets = collect(preg_split('/\r\n|\r|\n/', (string) data_get($section, 'bullets_text', '')))
                        ->map(fn (string $item): string => trim($item))
                        ->filter();
                @endphp

                <h2 class="h5 mt-3">{{ data_get($section, 'title') }}</h2>

                @if(filled(data_get($section, 'body')))
                    <p class="text-secondary">
                        {{ data_get($section, 'body') }}
                    </p>
                @endif

                @if($bullets->isNotEmpty())
                    <ul>
                        @foreach($bullets as $bullet)
                            <li>{{ $bullet }}</li>
                        @endforeach
                    </ul>
                @endif
            @endforeach

            @if(filled(data_get($legalHero, 'closing_note')))
                <p class="mt-3 mb-0 text-secondary{{ request()->routeIs('code-of-ethics') ? ' small' : '' }}">
                    {{ data_get($legalHero, 'closing_note') }}
                </p>
            @endif
                </div>
            </article>
        </section>
    </div>
</div>
