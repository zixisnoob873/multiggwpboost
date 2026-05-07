@php
    use App\Models\User;

    $role = User::normalizeRole($currentUserRole ?? $currentUser?->role);
    $isBooster = $role === User::ROLE_BOOSTER || ($isBoosterRoute ?? false);
    $items = $isBooster
        ? [
            ['label' => 'Dashboard', 'url' => route('booster-dashboard'), 'active' => request()->routeIs('booster-dashboard')],
            ['label' => 'Claim queue', 'url' => route('booster-claim-orders'), 'active' => request()->routeIs('booster-claim-orders')],
            ['label' => 'Orders', 'url' => route('booster-orders', ['view' => 'all']), 'active' => request()->routeIs('booster-orders')],
            ['label' => 'Chats', 'url' => route('booster-chats'), 'active' => request()->routeIs('booster-chats', 'booster-chats.show')],
            ['label' => 'Wallet', 'url' => route('booster-wallet'), 'active' => request()->routeIs('booster-wallet')],
        ]
        : [
            ['label' => 'Dashboard', 'url' => route('customer-dashboard'), 'active' => request()->routeIs('customer-dashboard')],
            ['label' => 'Orders', 'url' => route('allorders'), 'active' => request()->routeIs('allorders')],
            ['label' => 'Order chat', 'url' => route('user-chats'), 'active' => request()->routeIs('user-chats', 'user-chats.show')],
            ['label' => 'Upgrade', 'url' => route('customer-upgrade-order'), 'active' => request()->routeIs('customer-upgrade-order')],
            ['label' => 'New boost', 'url' => route('home').'#services', 'active' => false],
        ];
@endphp

<nav class="ggwp-portal-nav" aria-label="{{ $isBooster ? 'Booster workspace navigation' : 'Customer workspace navigation' }}">
    <div class="container ggwp-portal-nav__inner">
        <span class="ggwp-portal-nav__label">{{ $isBooster ? 'Booster workspace' : 'Customer workspace' }}</span>
        <div class="ggwp-portal-nav__links">
            @foreach($items as $item)
                <a
                    class="ggwp-portal-nav__link{{ $item['active'] ? ' is-active' : '' }}"
                    href="{{ $item['url'] }}"
                    @if($item['active']) aria-current="page" @endif
                >
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>
    </div>
</nav>
