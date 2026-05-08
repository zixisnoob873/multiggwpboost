@props([
    'game' => [],
    'faqs' => [],
])

@php
    $slug = data_get($game, 'slug', 'game');
    $gameShortName = data_get($game, 'shortName', data_get($game, 'name', 'Game'));
    $accordionId = 'gameFaqAccordion'.\Illuminate\Support\Str::studly($slug);
@endphp

<x-trust.faq-accordion
    :id="$accordionId"
    heading-id="gameFaqHeading"
    :faqs="$faqs"
    :kicker="$gameShortName.' FAQ'"
    title="Questions before you order"
    description="Safety, delivery, play mode, and VPN answers for this game."
/>
