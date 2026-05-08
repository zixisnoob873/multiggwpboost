@props([
    'type' => 'secure-payment',
    'title' => null,
    'body' => null,
])

@php
    $defaults = [
        'secure-payment' => ['icon' => 'SP', 'title' => 'Secure payments', 'body' => 'Protected checkout through approved payment providers.'],
        'vpn' => ['icon' => 'VPN', 'title' => 'VPN protection', 'body' => 'Location-aware handling for eligible account-shared orders.'],
        'fast-delivery' => ['icon' => 'FD', 'title' => 'Fast delivery', 'body' => 'Priority routing and clear timing for urgent goals.'],
        'support' => ['icon' => '24', 'title' => '24/7 support', 'body' => 'Live chat and order messaging stay close throughout delivery.'],
        'verified-booster' => ['icon' => 'VB', 'title' => 'Verified boosters', 'body' => 'Vetted players matched to game, service, and region.'],
    ];

    $badge = $defaults[$type] ?? $defaults['secure-payment'];
@endphp

<article {{ $attributes->class(['ggwp-trust-badge', 'ggwp-trust-badge--'.$type]) }} data-conversion-component="trust-badge">
    <span class="ggwp-trust-badge__icon" aria-hidden="true">{{ $badge['icon'] }}</span>
    <span class="ggwp-trust-badge__copy">
        <strong>{{ $title ?: $badge['title'] }}</strong>
        <span>{{ $body ?: $badge['body'] }}</span>
    </span>
</article>
