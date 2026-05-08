@props([
    'rating' => 5,
    'max' => 5,
    'label' => null,
    'class' => '',
])

@php
    $ratingValue = max(0, min((float) $max, (float) $rating));
    $maxValue = max(1, (int) $max);
    $filled = max(0, min($maxValue, (int) round($ratingValue)));
    $empty = max(0, $maxValue - $filled);
    $ratingLabel = $label ?: number_format($ratingValue, 1).' out of '.$maxValue.' stars';
@endphp

<span {{ $attributes->class(['ggwp-trust-stars', $class])->merge(['aria-label' => $ratingLabel]) }}>
    <span aria-hidden="true">@for($i = 0; $i < $filled; $i++)&#9733;@endfor@if($empty > 0)@for($i = 0; $i < $empty; $i++)&#9734;@endfor@endif</span>
</span>
