@extends('layouts.admin')

@section('title', 'GGWP Boost | Wallet Adjustments')

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide">
    @include('admin.partials.page-header', [
        'title' => 'Wallet Adjustments',
        'subtitle' => 'Record safe manual credits and deductions, then review the adjustment ledger with filters.',
        'actions' => [
            ['label' => 'Finance Overview', 'href' => route('admin-finance.index')],
            ['label' => 'Withdrawals', 'href' => route('admin-withdrawal-requests.index')],
        ],
    ])

    <div class="row g-3 mb-3">
        <div class="col-xl-5">
            <section class="card app-card admin-section-card">
                <div class="card-body">
                    <h2 class="h5 mb-3">Record Adjustment</h2>
                    <form method="POST" action="{{ route('admin-wallet-adjustments.store') }}" class="row g-3" data-loading-form>
                        @csrf
                        <div class="col-12">
                            <label class="form-label">Booster</label>
                            <select class="form-select @error('booster_id') is-invalid @enderror" name="booster_id" required>
                                <option value="">Select booster</option>
                                @foreach($boosters as $booster)
                                    <option value="{{ $booster->id }}" {{ (string) old('booster_id') === (string) $booster->id ? 'selected' : '' }}>
                                        {{ $booster->fullIdentity('Booster') }} · {{ $booster->email }}
                                    </option>
                                @endforeach
                            </select>
                            @error('booster_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Type</label>
                            <select class="form-select @error('type') is-invalid @enderror" name="type" required>
                                <option value="deduct" {{ old('type', 'deduct') === 'deduct' ? 'selected' : '' }}>Deduct</option>
                                <option value="add" {{ old('type') === 'add' ? 'selected' : '' }}>Add</option>
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Amount</label>
                            <input class="form-control @error('amount') is-invalid @enderror" name="amount" value="{{ old('amount') }}" placeholder="0.00" required>
                            @error('amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Reason</label>
                            <input class="form-control @error('reason') is-invalid @enderror" name="reason" value="{{ old('reason') }}" placeholder="Explain the change" required>
                            @error('reason')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <button class="btn btn-danger" type="submit" data-busy-label="Saving...">Save Adjustment</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <div class="col-xl-7">
            <form method="GET" action="{{ route('admin-wallet-adjustments.index') }}" class="card app-card admin-filters-card h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Filter Ledger</h2>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input class="form-control @error('search') is-invalid @enderror" name="search" value="{{ $adjustmentFilters['search'] ?? '' }}" placeholder="Reason or booster">
                            @error('search')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Booster</label>
                            <select class="form-select @error('booster_id') is-invalid @enderror" name="booster_id">
                                <option value="">All</option>
                                @foreach($boosters as $booster)
                                    <option value="{{ $booster->id }}" {{ (string) ($adjustmentFilters['booster_id'] ?? '') === (string) $booster->id ? 'selected' : '' }}>
                                        {{ $booster->publicIdentity('Booster') }}
                                    </option>
                                @endforeach
                            </select>
                            @error('booster_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Type</label>
                            <select class="form-select @error('type') is-invalid @enderror" name="type">
                                <option value="">All</option>
                                <option value="add" {{ ($adjustmentFilters['type'] ?? null) === 'add' ? 'selected' : '' }}>Credit</option>
                                <option value="deduct" {{ ($adjustmentFilters['type'] ?? null) === 'deduct' ? 'selected' : '' }}>Deduction</option>
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Per Page</label>
                            <select class="form-select @error('per_page') is-invalid @enderror" name="per_page">
                                @foreach([10, 20, 50, 100] as $size)
                                    <option value="{{ $size }}" {{ (int) ($adjustmentFilters['per_page'] ?? 20) === $size ? 'selected' : '' }}>{{ $size }}</option>
                                @endforeach
                            </select>
                            @error('per_page')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <button class="btn btn-danger" type="submit">Apply Filters</button>
                        <a class="btn btn-outline-light" href="{{ route('admin-wallet-adjustments.index') }}">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <section class="card app-card admin-section-card">
        <div class="card-body">
            @if($adjustments->isEmpty())
                @include('admin.partials.empty-state', [
                    'title' => 'No wallet adjustments found',
                    'copy' => 'Record a manual credit or deduction above, or clear the filters to review the full ledger.',
                ])
            @else
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Booster</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Reason</th>
                                <th>Admin</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($adjustments as $adjustment)
                                <tr>
                                    <td class="fw-semibold">{{ $adjustment->booster?->fullIdentity('Booster') }}</td>
                                    <td>{{ $adjustment->type === 'add' ? 'Credit' : 'Deduction' }}</td>
                                    <td>{{ $adjustment->type === 'add' ? '+' : '-' }}${{ number_format(($adjustment->amount_cents ?? 0) / 100, 2) }}</td>
                                    <td>{{ $adjustment->reason }}</td>
                                    <td>{{ $adjustment->admin?->fullIdentity('Admin') }}</td>
                                    <td>{{ $adjustment->created_at?->format('M j, Y g:i A') ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $adjustments->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </section>
</main>
@endsection
