@props([
    'game' => [],
    'reviews' => [],
])

@php
    $gameShortName = data_get($game, 'shortName', data_get($game, 'name', 'Game'));
@endphp

<x-trust.review-section
    id="gameReviewsHeading"
    :reviews="$reviews"
    kicker="Reviews"
    :title="'Trustpilot-style proof from '.$gameShortName.' players'"
    description="Short, scan-friendly reviews focused on support quality, speed, and delivery confidence."
    :service-fallback="$gameShortName.' Boosting'"
/>
