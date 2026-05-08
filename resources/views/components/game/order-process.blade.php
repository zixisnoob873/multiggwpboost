@props([
    'game' => [],
    'steps' => [],
])

@php
    $gameShortName = data_get($game, 'shortName', data_get($game, 'name', 'Game'));
@endphp

<x-trust.order-process
    id="orderProcessHeading"
    class="ggwp-game-process"
    :steps="$steps"
    kicker="Order process"
    title="From service pick to completion"
    :description="'A simple five-step flow keeps '.$gameShortName.' orders easy to start and easy to monitor.'"
/>
