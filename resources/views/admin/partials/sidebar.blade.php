@php
    use App\Support\AdminPermission;

    $adminUser = auth()->user();
    $adminRoleLabel = AdminPermission::roleLabel(AdminPermission::adminRole($adminUser));
    $navGroups = [
        'Dashboard' => [
            ['label' => 'Dashboard', 'route' => 'admin-dashboard', 'match' => 'admin-dashboard', 'ability' => 'dashboard.view'],
        ],
        'Operations' => [
            ['label' => 'Orders', 'route' => 'admin-total-order', 'match' => ['admin-total-order', 'admin-operations.orders', 'admin-orders.*'], 'ability' => 'operations.orders.view'],
            ['label' => 'Chats', 'route' => 'admin-chats', 'match' => ['admin-chats', 'admin-chats.*'], 'ability' => 'operations.chats.view'],
            ['label' => 'Manual Orders', 'route' => 'admin-custom-order', 'match' => 'admin-custom-order', 'ability' => 'operations.manual_orders.view'],
        ],
        'People' => [
            ['label' => 'Customers', 'route' => 'admin-customers.index', 'match' => 'admin-customers.*', 'ability' => 'people.customers.view'],
            ['label' => 'Boosters', 'route' => 'admin-boosters.index', 'match' => 'admin-boosters.*', 'ability' => 'people.boosters.view'],
            ['label' => 'Applications', 'route' => 'admin-booster-applications', 'match' => 'admin-booster-applications*', 'ability' => 'people.applications.view'],
            ['label' => 'Contact Inbox', 'route' => 'admin-contact-messages.index', 'match' => 'admin-contact-messages.*', 'ability' => 'people.inbox.view'],
        ],
        'Marketing' => [
            ['label' => 'Promotions', 'route' => 'admin-promotions.index', 'match' => 'admin-promotions.*', 'ability' => 'marketing.promotions.view'],
            ['label' => 'Promo Codes', 'route' => 'admin-promo-codes.index', 'match' => 'admin-promo-codes.*', 'ability' => 'marketing.promo_codes.view'],
            ['label' => 'Reviews', 'route' => 'admin-reviews.index', 'match' => 'admin-reviews.*', 'ability' => 'marketing.reviews.view'],
            ['label' => 'Blog Articles', 'route' => 'admin-blog-articles.index', 'match' => 'admin-blog-articles.*', 'ability' => 'marketing.blog.view'],
        ],
        'Content' => [
            ['label' => 'Content Home', 'route' => 'admin-content.index', 'match' => 'admin-content.index', 'ability' => 'content.hub.view'],
            ['label' => 'Pages', 'route' => 'admin-pages.index', 'match' => 'admin-pages.*', 'ability' => 'content.pages.view'],
            ['label' => 'FAQs', 'route' => 'admin-content.faqs', 'match' => 'admin-content.faqs', 'ability' => 'content.faqs.view'],
            ['label' => 'Featured Boosters', 'route' => 'admin-content.featured-boosters', 'match' => 'admin-content.featured-boosters', 'ability' => 'content.featured_boosters.view'],
            ['label' => 'Addon Tooltips', 'route' => 'admin-content.addon-tooltips', 'match' => 'admin-content.addon-tooltips', 'ability' => 'content.addon_tooltips.view'],
        ],
        'Finance' => [
            ['label' => 'Overview', 'route' => 'admin-finance.index', 'match' => 'admin-finance.index', 'ability' => 'finance.overview.view'],
            ['label' => 'Withdrawals', 'route' => 'admin-withdrawal-requests.index', 'match' => 'admin-withdrawal-requests.*', 'ability' => 'finance.withdrawals.view'],
            ['label' => 'Income Statement', 'route' => 'admin-income-statement', 'match' => 'admin-income-statement*', 'ability' => 'finance.income_statement.view'],
            ['label' => 'Wallet Adjustments', 'route' => 'admin-wallet-adjustments.index', 'match' => 'admin-wallet-adjustments.*', 'ability' => 'finance.wallet_adjustments.manage'],
        ],
        'System' => [
            ['label' => 'Maintenance Mode', 'route' => 'admin-system.maintenance', 'match' => 'admin-system.maintenance', 'ability' => 'system.maintenance.manage'],
            ['label' => 'Pricing', 'route' => 'admin-pricing.index', 'match' => 'admin-pricing.*', 'ability' => 'system.pricing.view'],
            ['label' => 'Settings', 'route' => 'admin-system.settings', 'match' => 'admin-system.settings*', 'ability' => 'system.settings.view'],
            ['label' => 'Audit Logs', 'route' => 'admin-system.audit-logs', 'match' => 'admin-system.audit-logs', 'ability' => 'system.audit.view'],
        ],
    ];
@endphp

<div class="admin-sidebar__brand">
    <a href="{{ route('admin-dashboard') }}" class="admin-sidebar__logo" aria-label="Open admin dashboard">
        <img src="{{ asset('assets/logo.png') }}" alt="GGWP Boost" decoding="async">
    </a>
    <div>
        <div class="admin-sidebar__eyebrow">Admin Panel</div>
        <div class="small text-secondary">{{ $adminRoleLabel }}</div>
    </div>
</div>

<nav class="admin-nav" aria-label="Admin navigation">
    @foreach($navGroups as $groupLabel => $items)
        @php
            $visibleItems = collect($items)->filter(fn (array $item) => AdminPermission::userCan($adminUser, $item['ability']));
        @endphp

        @if($visibleItems->isNotEmpty())
            <section class="admin-nav-group">
                <h2 class="admin-nav-group__title">{{ $groupLabel }}</h2>
                <div class="admin-nav-group__links">
                    @foreach($visibleItems as $item)
                        @php
                            $match = $item['match'];
                            $isActive = is_array($match)
                                ? collect($match)->contains(fn ($pattern) => request()->routeIs($pattern))
                                : request()->routeIs($match);
                        @endphp
                        <a class="admin-nav-link {{ $isActive ? 'is-active' : '' }}" href="{{ route($item['route']) }}">
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach
</nav>

<div class="admin-sidebar__footer">
    <div class="small text-secondary">{{ $adminUser?->fullIdentity('Admin') }}</div>
    <div class="small text-secondary">Access: {{ $adminRoleLabel }}</div>
</div>
