@extends('layouts.layout')

@section('title', 'Page Not Found')
@section('body_theme', 'dark')

@php
    $seo = [
        'title' => 'Page Not Found',
        'description' => 'The requested page could not be found.',
        'canonical' => url()->current(),
        'robots' => 'noindex,nofollow',
        'type' => 'website',
    ];
@endphp

@section('content')
<div class="ggwp-page-shell ggwp-error-shell">
    <section class="ggwp-error-card app-card" aria-labelledby="errorTitle">
        <div class="ggwp-error-card__content">
            <span class="ggwp-page-eyebrow">404 not found</span>
            <h1 id="errorTitle">This page left the queue</h1>
            <p class="text-secondary">The link may be old, moved, or typed incorrectly. Head back to the service hub or ask support to help find the right order workspace.</p>
            <div class="ggwp-error-card__actions">
                <a class="btn btn-danger" href="{{ route('home') }}#popular-services">Build a quote</a>
                <a class="btn btn-outline-light" href="{{ route('contact') }}">Contact support</a>
            </div>
        </div>
        <div class="ggwp-error-card__code" aria-hidden="true">404</div>
    </section>
</div>
@endsection
