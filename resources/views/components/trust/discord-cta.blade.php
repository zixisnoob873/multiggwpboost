@props([
    'id' => 'trustDiscordCtaHeading',
    'title' => 'Prefer Discord support?',
    'body' => 'Join the community support server for quick questions, custom requests, and order guidance.',
    'href' => null,
    'label' => 'Open Discord support',
])

@php
    $discordUrl = $href ?: (string) config('footer.support.community_url', 'https://discord.gg/2FD3qq9U');
@endphp

<section {{ $attributes->class('section-block ggwp-trust-cta ggwp-trust-cta--discord') }} aria-labelledby="{{ $id }}" data-conversion-component="discord-cta">
    <div>
        <span class="ggwp-home-section-kicker">Discord</span>
        <h2 id="{{ $id }}">{{ $title }}</h2>
        <p>{{ $body }}</p>
    </div>
    <a class="btn btn-outline-light" href="{{ $discordUrl }}" target="_blank" rel="noopener noreferrer">
        {{ $label }}
        <span class="visually-hidden">opens in a new tab</span>
    </a>
</section>
