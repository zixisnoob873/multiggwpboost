@php
    $footerConfig = (array) config('footer', []);
    $company = (array) ($footerConfig['company'] ?? []);
    $legalName = trim((string) ($company['legal_name'] ?? config('app.name', 'GGWP Boost')));
    $jurisdiction = trim((string) ($company['jurisdiction'] ?? ''));
    $companyLine = trim($legalName.($jurisdiction !== '' ? ', '.$jurisdiction : ''));
    $footerDisclaimer = trim((string) ($footerConfig['disclaimer'] ?? ''));

    $socials = collect((array) ($footerConfig['socials'] ?? []))
        ->map(function (array $social): array {
            $icon = trim((string) ($social['icon'] ?? ''));
            $url = trim((string) ($social['url'] ?? ''));

            return [
                'label' => trim((string) ($social['label'] ?? 'Social')),
                'url' => $url,
                'icon_url' => $icon !== '' && is_file(public_path('assets/socials/'.$icon))
                    ? asset('assets/socials/'.$icon)
                    : null,
            ];
        })
        ->filter(fn (array $social): bool => $social['icon_url'] !== null && $social['url'] !== '')
        ->values();

    $footerSections = [
        [
            'title' => 'Work With Us',
            'links' => [
                ['label' => 'Become a Booster', 'url' => route('become-booster')],
                ['label' => 'Reviews', 'url' => route('reviews')],
                ['label' => 'Blog', 'url' => route('blog.index')],
                ['label' => 'Contact', 'url' => route('contact')],
            ],
        ],
        [
            'title' => 'Policies',
            'links' => [
                ['label' => 'Terms', 'url' => route('terms-and-conditions')],
                ['label' => 'Privacy', 'url' => route('privacy-policy')],
                ['label' => 'Refund Policy', 'url' => route('refund-policy')],
                ['label' => 'Code of Ethics', 'url' => route('code-of-ethics')],
            ],
        ],
    ];
@endphp

<footer class="site-footer ggwp-footer" aria-labelledby="siteFooterHeading">
    <div class="container">
        <section class="ggwp-footer__shell">
            <div class="ggwp-footer__main">
                <div class="ggwp-footer__brand-column">
                    <div class="ggwp-footer__masthead">
                        <div class="ggwp-footer__identity">
                            <a class="ggwp-footer__brand" href="{{ route('home') }}">
                                <img
                                    src="{{ asset('assets/logo.png') }}"
                                    alt="{{ config('app.name', 'GGWP Boost') }}"
                                    class="ggwp-footer__brand-logo"
                                    loading="lazy"
                                    decoding="async"
                                >
                            </a>

                            <h2 id="siteFooterHeading" class="ggwp-footer__tagline">GGWPBoost — Premium Boosting Across Every Competitive Game.</h2>
                        </div>

                        @if($socials->isNotEmpty())
                            <nav class="ggwp-footer__social-wrap" aria-label="Community links">
                                <span class="ggwp-footer__social-label">Connect</span>

                                <div class="ggwp-footer__socials">
                                    @foreach($socials as $social)
                                        <a
                                            class="ggwp-footer__social-btn"
                                            href="{{ $social['url'] }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            aria-label="{{ $social['label'] }}"
                                            title="{{ $social['label'] }}"
                                        >
                                            <img
                                                src="{{ $social['icon_url'] }}"
                                                alt=""
                                                class="ggwp-footer__social-icon"
                                                loading="lazy"
                                                decoding="async"
                                            >
                                        </a>
                                    @endforeach
                                </div>
                            </nav>
                        @endif
                    </div>

                    <div class="ggwp-footer__actions">
                        <a class="btn btn-danger ggwp-footer__cta" href="{{ route('contact') }}">Contact Us</a>
                        <a class="btn btn-outline-light ggwp-footer__secondary" href="{{ route('become-booster') }}">Become a Booster</a>
                    </div>
                </div>

                <div class="ggwp-footer__nav-panel">
                    @foreach($footerSections as $section)
                        <nav class="ggwp-footer__nav-group" aria-label="{{ $section['title'] }}">
                            <h3 class="ggwp-footer__nav-title">{{ $section['title'] }}</h3>

                            <ul class="ggwp-footer__nav-links">
                                @foreach($section['links'] as $link)
                                    <li>
                                        <a class="ggwp-footer__nav-link" href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </nav>
                    @endforeach
                </div>
            </div>

            <div class="ggwp-footer__bottom">
                <div class="ggwp-footer__legal">
                    <p class="ggwp-footer__copyright">&copy; {{ now()->year }} {{ $companyLine }}</p>

                    @if($footerDisclaimer !== '')
                        <p class="ggwp-footer__disclaimer">{{ $footerDisclaimer }}</p>
                    @endif
                </div>
                <button type="button" class="ggwp-footer__cookie-settings" data-cookie-settings>
                    Cookie settings
                </button>
            </div>
        </section>
    </div>
</footer>
