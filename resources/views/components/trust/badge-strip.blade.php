@props([
    'badges' => null,
    'label' => 'GGWPBoost trust badges',
])

@php
    $items = collect($badges ?? [
        ['type' => 'secure-payment'],
        ['type' => 'vpn'],
        ['type' => 'fast-delivery'],
        ['type' => 'support'],
        ['type' => 'verified-booster'],
    ])->values();
@endphp

<section {{ $attributes->class('section-block ggwp-trust-badge-strip') }} aria-label="{{ $label }}" data-conversion-component="badge-strip">
    @foreach($items as $badge)
        <x-trust.badge
            :type="data_get($badge, 'type', 'secure-payment')"
            :title="data_get($badge, 'title')"
            :body="data_get($badge, 'body')"
        />
    @endforeach
</section>
