@extends('layouts.layout')

@section('title', 'Server Error')
@section('body_theme', 'dark')

@section('content')
<div class="ggwp-page-shell ggwp-error-shell">
    <section class="ggwp-error-card app-card" aria-labelledby="errorTitle">
        <div class="ggwp-error-card__content">
            <span class="ggwp-page-eyebrow">500 system</span>
            <h1 id="errorTitle">Something failed on our side</h1>
            <p class="text-secondary">Retry in a moment. If you were checking out or managing an order, contact support before submitting the same payment or action again.</p>
            <div class="ggwp-error-card__actions">
                <a class="btn btn-danger" href="{{ route('contact') }}">Contact support</a>
                <a class="btn btn-outline-light" href="{{ route('home') }}">Return home</a>
            </div>
        </div>
        <div class="ggwp-error-card__code" aria-hidden="true">500</div>
    </section>
</div>
@endsection
