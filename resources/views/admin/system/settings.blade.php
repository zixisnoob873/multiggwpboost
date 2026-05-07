@extends('layouts.admin')

@section('title', 'GGWP Boost | System Settings')

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide">
    @include('admin.partials.page-header', [
        'title' => 'System Settings',
        'subtitle' => 'Internal admin notices and integration visibility for the system module.',
        'actions' => [
            ['label' => 'Maintenance Mode', 'href' => route('admin-system.maintenance.index')],
            ['label' => 'Pricing', 'href' => route('admin-pricing.index'), 'class' => 'btn btn-danger btn-sm'],
            ['label' => 'Audit Logs', 'href' => route('admin-system.audit-logs')],
        ],
    ])

    <div class="row g-3">
        <div class="col-xl-7">
            <form method="POST" action="{{ route('admin-system.settings.update') }}" class="card app-card admin-section-card" data-loading-form data-dirty-form>
                @csrf
                @method('PUT')
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 class="h5 mb-1">Internal Notices</h2>
                            <p class="text-secondary mb-0">Short operational copy used inside the admin experience only.</p>
                        </div>
                        <button class="btn btn-danger" type="submit" data-busy-label="Saving...">Save Settings</button>
                    </div>

                    <div class="row g-3">
                        @foreach($settingsDefinitions as $key => $definition)
                            <div class="col-12">
                                <label class="form-label">{{ $definition['label'] }}</label>
                                <textarea class="form-control @error($key) is-invalid @enderror" name="{{ $key }}" rows="4">{{ old($key, $settingsValues[$key] ?? '') }}</textarea>
                                @if(!empty($definition['description']))
                                    <div class="form-text">{{ $definition['description'] }}</div>
                                @endif
                                @error($key)
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            </form>
        </div>

        <div class="col-xl-5">
            <section class="card app-card admin-section-card">
                <div class="card-body">
                    <h2 class="h5 mb-3">Integrations / Drivers</h2>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Setting</th>
                                    <th>Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($integrations as $integration)
                                    <tr>
                                        <td class="fw-semibold">{{ $integration['label'] }}</td>
                                        <td>{{ $integration['value'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>
@endsection
