@extends('layouts.admin')

@section('title', 'GGWP Boost | Audit Logs')

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide admin-page admin-page--dense">
    @include('admin.partials.page-header', [
        'title' => 'Audit Logs',
        'subtitle' => 'Sensitive admin activity.',
        'actions' => [
            ['label' => 'System Settings', 'href' => route('admin-system.settings')],
            ['label' => 'Maintenance Mode', 'href' => route('admin-system.maintenance.index')],
        ],
    ])

    <form method="GET" action="{{ route('admin-system.audit-logs') }}" class="card app-card admin-filters-card mb-3" data-loading-form>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input class="form-control @error('search') is-invalid @enderror" name="search" value="{{ $auditFilters['search'] ?? '' }}" placeholder="Action, subject, actor">
                    @error('search')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Module</label>
                    <select class="form-select @error('module') is-invalid @enderror" name="module">
                        <option value="">All</option>
                        @foreach($moduleOptions as $key => $option)
                            <option value="{{ $key }}" {{ ($auditFilters['module'] ?? null) === $key ? 'selected' : '' }}>{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                    @error('module')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Actor</label>
                    <select class="form-select @error('actor_id') is-invalid @enderror" name="actor_id">
                        <option value="">All</option>
                        @foreach($adminUsers as $adminUser)
                            <option value="{{ $adminUser->id }}" {{ (string) ($auditFilters['actor_id'] ?? '') === (string) $adminUser->id ? 'selected' : '' }}>
                                {{ $adminUser->fullIdentity('Admin') }} · {{ $adminUser->adminRoleLabel() }}
                            </option>
                        @endforeach
                    </select>
                    @error('actor_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">From</label>
                    <input type="date" class="form-control @error('created_from') is-invalid @enderror" name="created_from" value="{{ $auditFilters['created_from'] ?? '' }}">
                    @error('created_from')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">To</label>
                    <input type="date" class="form-control @error('created_to') is-invalid @enderror" name="created_to" value="{{ $auditFilters['created_to'] ?? '' }}">
                    @error('created_to')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <button class="btn btn-danger" type="submit" data-busy-label="Applying...">Apply Filters</button>
                <a class="btn btn-outline-light" href="{{ route('admin-system.audit-logs') }}">Reset</a>
            </div>
        </div>
    </form>

    <section class="card app-card admin-section-card">
        <div class="card-body">
            @if($auditLogs->isEmpty())
                @include('admin.partials.empty-state', [
                    'title' => 'No audit log entries',
                    'copy' => 'No entries matched this view.',
                ])
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Actor</th>
                                <th>Module</th>
                                <th>Action</th>
                                <th>Subject</th>
                                <th>Request</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($auditLogs as $log)
                                <tr>
                                    <td>{{ $log->created_at?->format('M j, Y g:i A') ?? '-' }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $log->actor?->fullIdentity('Admin') ?? 'System' }}</div>
                                        <div class="small text-secondary">{{ $log->actor?->adminRoleLabel() ?? $log->actor_role ?? '-' }}</div>
                                    </td>
                                    <td>{{ data_get($moduleOptions, $log->module.'.label', ucfirst((string) $log->module)) }}</td>
                                    <td>{{ $log->action }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $log->subject_label ?? '-' }}</div>
                                        @if($log->metadata)
                                            <details class="small mt-1">
                                                <summary>Metadata</summary>
                                                <pre class="small mb-0 mt-2">{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                            </details>
                                        @endif
                                    </td>
                                    <td>
                                        <div>{{ $log->method ?: '-' }}</div>
                                        <div class="small text-secondary">{{ $log->route_name ?: '-' }}</div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $auditLogs->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </section>
</main>
@endsection
