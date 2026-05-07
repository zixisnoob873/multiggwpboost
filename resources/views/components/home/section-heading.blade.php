@props([
    'id',
    'kicker' => null,
    'title',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'ggwp-marketplace-section-heading']) }}>
    <div>
        @if($kicker)
            <span class="ggwp-home-section-kicker">{{ $kicker }}</span>
        @endif

        <h2 id="{{ $id }}" class="ggwp-marketplace-section-heading__title">{{ $title }}</h2>

        @if($description)
            <p class="ggwp-marketplace-section-heading__copy">{{ $description }}</p>
        @endif
    </div>

    @if(trim($slot) !== '')
        <div class="ggwp-marketplace-section-heading__actions">
            {{ $slot }}
        </div>
    @endif
</div>
