@props([
    'title' => 'Nothing here yet',
    'description' => null,
    'icon' => null,
])

<div {{ $attributes->class('ggwp-empty-state') }}>
    @if($icon)
        <div class="ggwp-empty-state__icon" aria-hidden="true">{{ $icon }}</div>
    @endif
    <h2 class="h4 mb-2">{{ $title }}</h2>
    @if($description)
        <p class="mb-0">{{ $description }}</p>
    @endif
    {{ $slot }}
</div>
