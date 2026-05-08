@extends('layouts.layout')

@section('main_classes', 'container site-main ggwp-game-page')

@section('content')
    <x-game.hero
        :game="$activeGame ?? []"
        :card="$gameCard ?? []"
        :description="data_get($seo ?? [], 'description')"
        :service-count="count($gameServices ?? [])"
    />

    <x-game.service-grid :game="$activeGame ?? []" :services="$gameServices ?? []" />
    <x-game.trust-points :game="$activeGame ?? []" :items="$whyChooseItems ?? []" />
    <x-game.order-process :game="$activeGame ?? []" :steps="$orderProcessSteps ?? []" />
    <x-game.faq-list :game="$activeGame ?? []" :faqs="$faqs ?? []" />
    <x-game.review-grid :game="$activeGame ?? []" :reviews="$reviews ?? []" />
    <x-trust.discord-cta
        id="gameDiscordCtaHeading"
        :title="'Need '.data_get($activeGame ?? [], 'shortName', data_get($activeGame ?? [], 'name', 'game')).' support on Discord?'"
        body="Ask about service fit, delivery mode, and custom order details before opening checkout."
    />
    <x-game.related-services :game="$activeGame ?? []" :services="$relatedServices ?? []" />
@endsection
