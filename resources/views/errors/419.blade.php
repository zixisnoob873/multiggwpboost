@extends('layouts.layout')

@section('title', 'Session Expired')
@section('body_theme', 'dark')

@section('content')
<div class="ggwp-page-shell ggwp-error-shell">
    <section class="ggwp-error-card app-card" aria-labelledby="errorTitle">
        <div class="ggwp-error-card__content">
            <span class="ggwp-page-eyebrow">419 session</span>
            <h1 id="errorTitle">Your session expired</h1>
            <p class="text-secondary">Refresh the page and submit the form again. This protects checkout, account, and order actions from stale sessions.</p>
            <div class="ggwp-error-card__actions">
                <a class="btn btn-danger" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('home') }}">Go back</a>
                <a class="btn btn-outline-light" href="{{ route('login') }}">Sign in again</a>
            </div>
        </div>
        <div class="ggwp-error-card__code" aria-hidden="true">419</div>
    </section>
</div>
@endsection
