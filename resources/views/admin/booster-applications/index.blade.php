@extends('layouts.admin')

@section('title', 'GGWP Boost | Booster Applications')

@php
    use App\Models\BoosterApplication;

    $statusOptions = BoosterApplication::statusOptions();
@endphp

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide admin-page admin-page--dense">
    @include('admin.partials.page-header', [
        'title' => 'Booster Applications',
        'subtitle' => 'Hiring queue.',
        'actions' => [
            ['label' => 'Boosters', 'href' => route('admin-boosters.index')],
        ],
    ])

    <div class="admin-chip-row mb-3">
        @foreach($statusOptions as $statusKey => $statusLabel)
            <a class="admin-chip text-decoration-none" href="{{ route('admin-booster-applications', array_merge(request()->query(), ['status' => $statusKey, 'page' => 1])) }}">
                {{ $statusLabel }}: {{ $applicationStats[$statusKey] ?? 0 }}
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin-booster-applications') }}" class="card app-card admin-filters-card mb-3" data-loading-form>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-5">
                    <label class="form-label">Search</label>
                    <input class="form-control @error('search') is-invalid @enderror" name="search" value="{{ $applicationFilters['search'] ?? '' }}" placeholder="Name, email, discord, rank">
                    @error('search')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select @error('status') is-invalid @enderror" name="status">
                        <option value="">All</option>
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}" {{ ($applicationFilters['status'] ?? null) === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sort</label>
                    <select class="form-select @error('sort') is-invalid @enderror" name="sort">
                        <option value="created_at" {{ ($applicationFilters['sort'] ?? 'created_at') === 'created_at' ? 'selected' : '' }}>Submitted</option>
                        <option value="name" {{ ($applicationFilters['sort'] ?? null) === 'name' ? 'selected' : '' }}>Name</option>
                        <option value="status" {{ ($applicationFilters['sort'] ?? null) === 'status' ? 'selected' : '' }}>Status</option>
                        <option value="peak_rank" {{ ($applicationFilters['sort'] ?? null) === 'peak_rank' ? 'selected' : '' }}>Peak Rank</option>
                    </select>
                    @error('sort')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Per Page</label>
                    <select class="form-select @error('per_page') is-invalid @enderror" name="per_page">
                        @foreach([10, 20, 50, 100] as $size)
                            <option value="{{ $size }}" {{ (int) ($applicationFilters['per_page'] ?? 20) === $size ? 'selected' : '' }}>{{ $size }}</option>
                        @endforeach
                    </select>
                    @error('per_page')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <button class="btn btn-danger" type="submit" data-busy-label="Applying...">Apply Filters</button>
                <a class="btn btn-outline-light" href="{{ route('admin-booster-applications') }}">Reset</a>
            </div>
        </div>
    </form>

    <section class="card app-card admin-section-card">
        <div class="card-body">
            @if($applications->isEmpty())
                @include('admin.partials.empty-state', [
                    'title' => 'No applications matched these filters',
                    'copy' => 'Clear the filters or wait for the next public booster application to enter the pipeline.',
                ])
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Applicant</th>
                                <th>Ranks</th>
                                <th>Discord</th>
                                <th>Status</th>
                                <th>Reviewed</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($applications as $application)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $application->name }}</div>
                                        <div class="small text-secondary">{{ $application->email }} · {{ $application->nickname }}</div>
                                    </td>
                                    <td>{{ $application->current_rank }} → {{ $application->peak_rank }}</td>
                                    <td>{{ $application->discord }}</td>
                                    <td>{{ $application->statusLabel() }}</td>
                                    <td>{{ $application->reviewed_at?->format('M j, Y') ?? 'Not reviewed' }}</td>
                                    <td class="text-end">
                                        <div class="d-flex flex-wrap justify-content-end gap-2">
                                            <a class="btn btn-outline-light btn-sm" href="{{ route('admin-booster-applications.edit', $application) }}">Review</a>
                                            @if($application->isConverted() && $application->convertedBooster?->nickname)
                                                <a class="btn btn-outline-light btn-sm" href="{{ route('admin-boosters.edit', ['booster' => $application->convertedBooster->nickname]) }}">Open Booster</a>
                                            @elseif(! $application->isConverted())
                                                <a class="btn btn-danger btn-sm" href="{{ route('admin-booster-applications.convert', $application) }}">Convert</a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $applications->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </section>
</main>
@endsection
