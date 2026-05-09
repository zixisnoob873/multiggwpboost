@props([
    'as' => 'article',
    'variant' => 'default',
    'padding' => true,
])

<{{ $as }} {{ $attributes->class([
    'card app-card ggwp-card',
    'ggwp-card--clickable' => $variant === 'clickable',
]) }}>
    @if($padding)
        <div class="card-body">
            {{ $slot }}
        </div>
    @else
        {{ $slot }}
    @endif
</{{ $as }}>
