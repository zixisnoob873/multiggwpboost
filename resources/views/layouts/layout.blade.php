@php
    use App\Models\User;
    use App\Support\PageTitle;
    use App\Support\Privacy\CookieConsent;

    $seo = is_array($seo ?? null) ? $seo : [];
    $bodyTheme = trim($__env->yieldContent('body_theme')) ?: 'dark';
    $mainClasses = trim($__env->yieldContent('main_classes')) ?: 'container site-main';
    $hideSiteNav = trim($__env->yieldContent('hide_site_nav')) === '1';
    $hideSiteFooter = trim($__env->yieldContent('hide_site_footer')) === '1';
    $isAdminRoute = request()->routeIs('admin-*', 'admin.*') || request()->is('admin/*');
    $isBoosterRoute = request()->routeIs('booster-*') || request()->is('booster/*', 'boost/*');
    $isUserRoute = request()->is('user/*');
    $isCustomerChatRoute = request()->routeIs('user-chats', 'user-chats.show');
    $shouldLoadTawkWidget = ! $isAdminRoute && ! $isBoosterRoute && ! $isCustomerChatRoute;
    $cookieConsent = CookieConsent::fromRequest(request());
    $canLoadAnalytics = CookieConsent::allows($cookieConsent, CookieConsent::CATEGORY_ANALYTICS);
    $canLoadSupport = CookieConsent::allows($cookieConsent, CookieConsent::CATEGORY_SUPPORT);
    $isAuthPage = request()->routeIs('login', 'login.submit', 'signup', 'signup.submit', 'password.*', 'oauth.*');
    $navItems = [
        ['label' => 'Home', 'url' => route('home'), 'active' => request()->routeIs('home')],
        ['label' => 'Services', 'url' => route('home').'#services', 'active' => false],
        ['label' => 'Reviews', 'url' => route('reviews'), 'active' => request()->routeIs('reviews')],
        ['label' => 'FAQ', 'url' => route('faq'), 'active' => request()->routeIs('faq')],
        ['label' => 'Blog', 'url' => route('blog.index'), 'active' => request()->routeIs('blog.*')],
        ['label' => 'Contact', 'url' => route('contact'), 'active' => request()->routeIs('contact', 'contact.submit')],
    ];
    $currentUser = Auth::user();
    $currentUserRole = $currentUser ? User::normalizeRole($currentUser->role) : null;
    $showDashboardNav = ($currentUser?->isAdminUser() ?? false) || $currentUserRole === User::ROLE_BOOSTER;
    $accountNavLabel = $showDashboardNav ? 'Dashboard' : 'Profile';
    $accountNavRoute = match (true) {
        $currentUser?->isAdminUser() ?? false => 'admin-dashboard',
        $currentUserRole === User::ROLE_BOOSTER => 'booster-dashboard',
        default => 'customer-dashboard',
    };
    $broadcastConnection = config('broadcasting.default');
    $pusherOptions = config('broadcasting.connections.pusher.options', []);
    $broadcastEnabled = $broadcastConnection === 'pusher' && filled(config('broadcasting.connections.pusher.key'));
    $broadcastConfig = [
        'enabled' => $broadcastEnabled,
        'key' => config('broadcasting.connections.pusher.key'),
        'cluster' => $pusherOptions['cluster'] ?? null,
        'host' => $pusherOptions['host'] ?? request()->getHost(),
        'port' => (int) ($pusherOptions['port'] ?? 6001),
        'scheme' => $pusherOptions['scheme'] ?? 'http',
        'forceTLS' => (bool) ($pusherOptions['useTLS'] ?? false),
        'path' => trim((string) config('websockets.path', 'laravel-websockets'), '/'),
        'authEndpoint' => url('/broadcasting/auth'),
    ];
    $seoTitle = trim((string) ($seo['title'] ?? ''));
    $pageTitle = $seoTitle !== ''
        ? PageTitle::format($seoTitle)
        : PageTitle::resolve(trim($__env->yieldContent('title')));
    $currentUserDisplayName = null;
    $currentUserAppState = null;

    if ($currentUser) {
        $currentUserDisplayName = $currentUser->isAdminUser()
            ? $currentUser->fullIdentity('User')
            : $currentUser->publicIdentity('User');

        $currentUserAppState = [
            'id' => $currentUser->id,
            'name' => $currentUserDisplayName,
            'role' => $currentUserRole,
            'nickname' => $currentUser->nickname,
            'display_name' => $currentUserDisplayName,
        ];
    }
@endphp

<!doctype html>
<html lang="en">

<head>
    @if($canLoadAnalytics)
        <!-- Google tag (gtag.js) -->
        <script nonce="{{ $cspNonce ?? '' }}" data-ggwp-consent-script="analytics" async src="https://www.googletagmanager.com/gtag/js?id=G-9J3GNV5WSX"></script>
        <script nonce="{{ $cspNonce ?? '' }}">
          window.dataLayer = window.dataLayer || [];
          function gtag(){dataLayer.push(arguments);}
          gtag('js', new Date());

          gtag('config', 'G-9J3GNV5WSX');
          window.ggwpAnalyticsLoaded = true;
        </script>
    @endif
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $pageTitle }}</title>
    @include('partials.seo-meta', ['seo' => $seo])
    @include('partials.favicons')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@299&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script nonce="{{ $cspNonce ?? '' }}">
        window.appState = {
            loginUrl: "{{ route('login') }}",
            loggedIn: @json(Auth::check()),
            userRole: @json(optional(Auth::user())->role),
            csrfToken: @json(csrf_token()),
            apiBase: '',
            calculatePriceUrl: "{{ route('pricing.calculate') }}",
            pricingConfigUrl: "{{ route('pricing.config') }}",
            promoPreviewUrl: "{{ route('checkout.promo.preview') }}",
            valorantAgents: @json($ggwpValorantAgents ?? []),
            user: @json($currentUserAppState),
            broadcast: @json($broadcastConfig),
        };
        window.ggwpApiBase = () => window.appState.apiBase || '';
        window.ggwpProductConfig = @json($ggwpProductConfig ?? []);
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>

<body class="site-shell{{ $isAdminRoute ? ' page-admin' : '' }}{{ $isBoosterRoute ? ' page-booster' : '' }}{{ $isUserRoute ? ' page-user' : '' }}{{ $isAuthPage ? ' page-auth' : '' }}" data-bs-theme="{{ $bodyTheme }}">
   <a class="ggwp-skip-link" href="#appMain">Skip to main content</a>
   @unless($hideSiteNav)
   <nav class="navbar navbar-expand-lg sticky-top glass-nav border-bottom border-secondary-subtle site-nav {{ $bodyTheme === 'dark' ? 'navbar-dark' : 'navbar-light' }}" aria-label="Primary navigation">
    <div class="container py-1">
        <a class="navbar-brand brand-title" href="{{ route('home') }}">
            <img src="{{ asset('assets/logo.png') }}" alt="ggwp" class="brand-logo" decoding="async" fetchpriority="high">
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navHome"
            aria-controls="navHome" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navHome">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">

                @foreach($navItems as $navItem)
                    <li class="nav-item">
                        <a
                            class="nav-link{{ $navItem['active'] ? ' active' : '' }}"
                            href="{{ $navItem['url'] }}"
                            @if($navItem['active']) aria-current="page" @endif
                        >{{ $navItem['label'] }}</a>
                    </li>
                @endforeach

                @auth
                    <li class="nav-item">
                        <a
                            class="nav-link{{ request()->routeIs($accountNavRoute) ? ' active' : '' }}"
                            href="{{ route($accountNavRoute) }}"
                            data-nav="profile"
                            @if(request()->routeIs($accountNavRoute)) aria-current="page" @endif
                        >{{ $accountNavLabel }}</a>
                    </li>
                @endauth
                @guest
                    <li class="nav-item nav-item-auth">
                        <div class="d-flex flex-column flex-lg-row align-items-stretch align-items-lg-center gap-2 mt-2 mt-lg-0">
                            <a class="btn nav-auth-btn nav-login-btn {{ $bodyTheme === 'dark' ? 'btn-outline-light' : 'btn-outline-dark' }}" href="{{ route('login') }}">
                                Login
                            </a>
                            <a class="btn nav-auth-btn nav-signup-btn {{ $bodyTheme === 'dark' ? 'btn-outline-light' : 'btn-outline-dark' }}" href="{{ route('signup') }}">
                                Sign Up
                            </a>
                        </div>
                    </li>
                @endguest

                @auth
                    <li class="nav-item nav-item-auth">
                        <div class="d-flex mt-2 mt-lg-0">
                            <button
                              class="btn btn-outline-danger nav-auth-btn nav-logout-btn"
                              type="submit"
                              form="logoutForm"
                            >Logout</button>
                        </div>
                    </li>
                @endauth

            </ul>
        </div>
    </div>
</nav>
@endunless

@if(! $hideSiteNav && ! $isAdminRoute && Auth::check() && ($isBoosterRoute || $isUserRoute))
    @include('partials.portal-nav', [
        'currentUserRole' => $currentUserRole,
        'currentUser' => $currentUser,
        'isBoosterRoute' => $isBoosterRoute,
        'isUserRoute' => $isUserRoute,
    ])
@endif

<form id="logoutForm" action="{{ route('logout') }}" method="POST" class="d-none">
    @csrf
</form>

    <main id="appMain" class="{{ $mainClasses }}" tabindex="-1">
        @yield('content')
    </main>

    <x-agent-selectors.modal-root />

    @if(($showPublicSocialProof ?? false) && ! empty($publicSocialProofItems ?? []))
        @include('partials.public-social-proof', ['items' => $publicSocialProofItems])
    @endif

    @if(! $hideSiteFooter && ! $isAdminRoute && ! $isBoosterRoute && ! $isUserRoute)
        @include('partials.site-footer')
    @endif

    @include('partials.cookie-consent', [
        'cookieConsent' => $cookieConsent,
        'shouldShowCookieBanner' => CookieConsent::shouldShowBanner($cookieConsent),
        'shouldLoadTawkWidget' => $shouldLoadTawkWidget,
    ])

    <script nonce="{{ $cspNonce ?? '' }}" src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
    @if($shouldLoadTawkWidget && $canLoadSupport)
        @include('partials.tawk-widget')
    @endif
</body>

</html>
