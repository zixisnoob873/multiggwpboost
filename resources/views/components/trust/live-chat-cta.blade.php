@props([
    'id' => 'trustLiveChatCtaHeading',
    'title' => 'Need help choosing?',
    'body' => 'Open live chat and we will help match the safest service, delivery mode, and add-ons to your goal.',
    'href' => null,
    'label' => 'Open live chat',
])

<section {{ $attributes->class('section-block ggwp-trust-cta ggwp-trust-cta--live-chat') }} aria-labelledby="{{ $id }}" data-conversion-component="live-chat-cta">
    <div>
        <span class="ggwp-home-section-kicker">Live support</span>
        <h2 id="{{ $id }}">{{ $title }}</h2>
        <p>{{ $body }}</p>
    </div>
    <a class="btn btn-danger ggwp-live-chat-cta" href="{{ $href ?: route('contact').'#contactForm' }}" data-live-chat-trigger>{{ $label }}</a>
</section>
