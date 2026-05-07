@extends('layouts.admin')

@section('title', 'GGWP Boost | Booster Application')

@php
    use App\Models\BoosterApplication;

    $statusOptions = collect(BoosterApplication::transitionTargets($application->status, $application->isConverted()))
        ->mapWithKeys(fn (string $status): array => [$status => BoosterApplication::statusOptions()[$status]])
        ->all();
    $convertedBoosterNickname = $application->convertedBooster?->nickname;
    $headerActions = [
        ['label' => 'Back To Applications', 'href' => route('admin-booster-applications')],
    ];

    if ($convertedBoosterNickname) {
        $headerActions[] = ['label' => 'Open Booster', 'href' => route('admin-boosters.edit', ['booster' => $convertedBoosterNickname]), 'class' => 'btn btn-outline-light btn-sm'];
    } elseif (! $application->isConverted()) {
        $headerActions[] = ['label' => 'Convert', 'href' => route('admin-booster-applications.convert', $application), 'class' => 'btn btn-danger btn-sm'];
    }
@endphp

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide admin-page admin-page--dense">
    @include('admin.partials.page-header', [
        'title' => 'Application: '.$application->name,
        'subtitle' => 'Review status, notes, and conversion state.',
        'meta' => [
            'Status: '.$application->statusLabel(),
            'Submitted '.$application->created_at?->format('M j, Y g:i A'),
        ],
        'actions' => $headerActions,
    ])

    <div class="row g-2">
        <div class="col-xl-8">
            <section class="card app-card admin-section-card mb-2">
                <div class="card-body">
                    <h2 class="h5 mb-3">Applicant Details</h2>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input class="form-control" value="{{ $application->name }}" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nickname</label>
                            <input class="form-control" value="{{ $application->nickname }}" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input class="form-control" value="{{ $application->email }}" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Discord</label>
                            <input class="form-control" value="{{ $application->discord }}" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Current Rank</label>
                            <input class="form-control" value="{{ $application->current_rank }}" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Peak Rank</label>
                            <input class="form-control" value="{{ $application->peak_rank }}" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Average Time</label>
                            <input class="form-control" value="{{ $application->average_time }}" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Regions</label>
                            <input class="form-control" value="{{ collect($application->regions ?? [])->implode(', ') }}" disabled>
                        </div>
                        <div class="col-md-6">
                            <a class="btn btn-outline-light w-100" href="{{ $application->main_account_tracker }}" target="_blank" rel="noopener">Open Tracker</a>
                        </div>
                        <div class="col-md-6">
                            @if($application->marketplace_profile)
                                <a class="btn btn-outline-light w-100" href="{{ $application->marketplace_profile }}" target="_blank" rel="noopener">Open Marketplace Profile</a>
                            @else
                                <button class="btn btn-outline-light w-100" type="button" disabled>No Marketplace Profile</button>
                            @endif
                        </div>
                    </div>
                </div>
            </section>

            <form method="POST" action="{{ route('admin-booster-applications.update', $application) }}" class="card app-card admin-section-card" data-loading-form data-dirty-form data-validate-form novalidate>
                @csrf
                @method('PATCH')
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">Handling</h2>
                        <button class="btn btn-danger" type="submit" data-busy-label="Saving...">Save</button>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select class="form-select @error('status') is-invalid @enderror" name="status">
                                @foreach($statusOptions as $value => $label)
                                    <option value="{{ $value }}" {{ old('status', $application->status) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Internal Notes</label>
                            <textarea class="form-control @error('admin_notes') is-invalid @enderror" name="admin_notes" rows="5" maxlength="2000">{{ old('admin_notes', $application->admin_notes) }}</textarea>
                            @error('admin_notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-xl-4">
            <section class="card app-card admin-section-card">
                <div class="card-body">
                    <h2 class="h5 mb-3">Snapshot</h2>
                    <div class="d-flex justify-content-between py-2 border-bottom border-secondary-subtle">
                        <span>Current Status</span>
                        <strong>{{ $application->statusLabel() }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom border-secondary-subtle">
                        <span>Reviewed By</span>
                        <strong>{{ $application->reviewer?->fullIdentity('Not reviewed') ?? 'Not reviewed' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom border-secondary-subtle">
                        <span>Reviewed At</span>
                        <strong>{{ $application->reviewed_at?->format('M j, Y g:i A') ?? 'Not reviewed' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2">
                        <span>Converted Booster</span>
                        <strong>{{ $application->convertedBooster?->fullIdentity('Not converted') ?? 'Not converted' }}</strong>
                    </div>
                    @if($convertedBoosterNickname)
                        <a class="btn btn-outline-light btn-sm mt-3" href="{{ route('admin-boosters.edit', ['booster' => $convertedBoosterNickname]) }}">Open Booster</a>
                    @endif
                </div>
            </section>
        </div>
    </div>
</main>
@endsection
