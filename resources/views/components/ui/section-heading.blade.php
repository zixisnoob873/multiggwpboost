@props([
    'eyebrow' => null,
    'title',
    'description' => null,
    'align' => 'start',
])

<header {{ $attributes->class([
    'ggwp-section-heading',
    'ggwp-section-heading--center' => $align === 'center',
]) }}>
    @if($eyebrow)
        <span class="ggwp-page-eyebrow">{{ $eyebrow }}</span>
    @endif
    <h2 class="mb-0">{{ $title }}</h2>
    @if($description)
        <p class="mb-0">{{ $description }}</p>
    @endif
</header>
