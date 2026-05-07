@extends('layouts.layout')

@section('title', 'Access Restricted')
@section('body_theme', 'dark')

@php
    $viewer = auth()->user();
    $viewerRole = \App\Models\User::normalizeRole($viewer?->role);
    $dashboardUrl = route('home');

    if ($viewer?->isAdminUser()) {
        $dashboardUrl = route('admin-dashboard');
    } elseif ($viewerRole === \App\Models\User::ROLE_BOOSTER) {
        $dashboardUrl = route('booster-dashboard');
    } elseif ($viewer) {
        $dashboardUrl = route('customer-dashboard');
    }
@endphp

@section('content')
<div class="ggwp-page-shell ggwp-error-shell">
    <section class="ggwp-error-card app-card" aria-labelledby="errorTitle">
        <div class="ggwp-error-card__content">
            <span class="ggwp-page-eyebrow">403 access</span>
            <h1 id="errorTitle">This area is restricted</h1>
            <p class="text-secondary">Your account does not have access to this workspace. Return to your dashboard or contact support if this looks wrong.</p>
            <div class="ggwp-error-card__actions">
                <a class="btn btn-danger" href="{{ $dashboardUrl }}">Open dashboard</a>
                <a class="btn btn-outline-light" href="{{ route('contact') }}">Contact support</a>
            </div>
        </div>
        <div class="ggwp-error-card__code" aria-hidden="true">403</div>
    </section>
</div>
@endsection
