@extends('layouts.layout')

@section('title', 'GGWP Boost | Maintenance Mode')

@section('content')
<main class="ggwp-page-shell ggwp-page-shell--wide">
    <div class="ggwp-page-header mb-3">
        <div class="ggwp-page-header__copy">
            <h1 class="mb-0">Maintenance Mode</h1>
            <p class="text-secondary mb-0">Dedicated system control surface for safe maintenance toggles. The secure confirmation flow remains intact and no longer dominates the dashboard.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 ggwp-page-actions">
            <a class="btn btn-outline-light" href="{{ route('admin-system.settings') }}">Settings</a>
            <a class="btn btn-outline-light" href="{{ route('admin-system.audit-logs') }}">Audit Logs</a>
        </div>
    </div>

    @include('admin.partials.flash')

    @include('admin.system.partials.maintenance-panel', [
        'maintenanceCardTitle' => 'Maintenance mode control',
        'maintenanceCardDescription' => 'Public traffic is redirected to the maintenance page while protected admin, auth, blog, payment, and webhook flows stay available.',
    ])
</main>
@endsection
