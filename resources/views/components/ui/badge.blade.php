@props([
    'tone' => 'secondary',
])

<span {{ $attributes->class(['badge', "text-bg-{$tone}"]) }}>{{ $slot }}</span>
