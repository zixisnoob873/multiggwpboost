@extends('layouts.admin')

@section('title', 'GGWP Boost | Withdrawals')

@php
    $selectedStatus = $withdrawalFilters['status'] ?? \App\Models\WithdrawalRequest::STATUS_PENDING;
    $statusTabs = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'paid' => 'Paid',
    ];
@endphp

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide admin-page admin-page--dense">
    @include('admin.partials.page-header', [
        'title' => 'Withdrawals',
        'subtitle' => 'Payout review queue.',
        'actions' => [
            ['label' => 'Finance Overview', 'href' => route('admin-finance.index')],
            ['label' => 'Wallet Adjustments', 'href' => route('admin-wallet-adjustments.index')],
        ],
    ])

    <div class="d-flex flex-wrap gap-2 mb-2">
        @foreach($statusTabs as $value => $label)
            <a class="btn {{ $selectedStatus === $value ? 'btn-danger' : 'btn-outline-light' }} btn-sm" href="{{ route('admin-withdrawal-requests.index', array_merge(request()->query(), ['status' => $value, 'page' => 1])) }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin-withdrawal-requests.index') }}" class="card app-card admin-filters-card mb-3" data-loading-form>
        <div class="card-body">
            <input type="hidden" name="status" value="{{ $selectedStatus }}">

            <div class="row g-2">
                <div class="col-md-5">
                    <label class="form-label">Search</label>
                    <input class="form-control @error('search') is-invalid @enderror" name="search" value="{{ $withdrawalFilters['search'] ?? '' }}" placeholder="Booster name or email">
                    @error('search')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sort</label>
                    <select class="form-select @error('sort') is-invalid @enderror" name="sort">
                        <option value="created_at" {{ ($withdrawalFilters['sort'] ?? 'created_at') === 'created_at' ? 'selected' : '' }}>Created</option>
                        <option value="amount_cents" {{ ($withdrawalFilters['sort'] ?? null) === 'amount_cents' ? 'selected' : '' }}>Amount</option>
                        <option value="status" {{ ($withdrawalFilters['sort'] ?? null) === 'status' ? 'selected' : '' }}>Status</option>
                        <option value="processed_at" {{ ($withdrawalFilters['sort'] ?? null) === 'processed_at' ? 'selected' : '' }}>Processed</option>
                    </select>
                    @error('sort')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Per Page</label>
                    <select class="form-select @error('per_page') is-invalid @enderror" name="per_page">
                        @foreach([10, 20, 50, 100] as $size)
                            <option value="{{ $size }}" {{ (int) ($withdrawalFilters['per_page'] ?? 20) === $size ? 'selected' : '' }}>{{ $size }}</option>
                        @endforeach
                    </select>
                    @error('per_page')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <button class="btn btn-danger" type="submit" data-busy-label="Applying...">Apply Filters</button>
                <a class="btn btn-outline-light" href="{{ route('admin-withdrawal-requests.index') }}">Reset</a>
            </div>
        </div>
    </form>

    <section class="card app-card admin-section-card">
        <div class="card-body">
            @if($requests->isEmpty())
                @include('admin.partials.empty-state', [
                    'title' => 'No withdrawals matched these filters',
                    'copy' => 'No requests matched this view.',
                ])
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Booster</th>
                                <th>Requested</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Notes</th>
                                <th>Processed</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($requests as $request)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $request->booster?->fullIdentity('Booster') }}</div>
                                        <div class="small text-secondary">{{ $request->booster?->email }}</div>
                                    </td>
                                    <td>{{ $request->created_at?->format('M j, Y g:i A') ?? '-' }}</td>
                                    <td class="fw-semibold">${{ number_format(($request->amount_cents ?? 0) / 100, 2) }}</td>
                                    <td><span class="badge text-bg-secondary">{{ ucfirst($request->status) }}</span></td>
                                    <td>
                                        <div>{{ $request->notes ?: '-' }}</div>
                                        @if(data_get($request->metadata, 'payout_method') || data_get($request->metadata, 'transaction_reference'))
                                            <div class="small text-secondary">
                                                {{ data_get($request->metadata, 'payout_method', 'Payout') }}
                                                @if(data_get($request->metadata, 'transaction_reference'))
                                                    · {{ data_get($request->metadata, 'transaction_reference') }}
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td>{{ $request->processed_at?->format('M j, Y g:i A') ?? 'Pending' }}</td>
                                    <td class="text-end">
                                        @if($request->status === 'pending')
                                            <div class="d-grid gap-2">
                                                <form method="POST" action="{{ route('admin-withdrawal-requests.update', $request) }}" class="d-grid gap-2" data-loading-form data-confirm-submit="Approve this withdrawal request?">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="approved">
                                                    <input class="form-control form-control-sm" name="payout_method" placeholder="Payout method">
                                                    <input class="form-control form-control-sm" name="transaction_reference" placeholder="Reference">
                                                    <input class="form-control form-control-sm" name="estimated_arrival" placeholder="Arrival">
                                                    <button class="btn btn-outline-success btn-sm" type="submit" data-busy-label="Approving...">Approve</button>
                                                </form>
                                                <form method="POST" action="{{ route('admin-withdrawal-requests.update', $request) }}" class="d-grid gap-2" data-loading-form data-confirm-submit="Reject this withdrawal request?">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="rejected">
                                                    <input class="form-control form-control-sm" name="notes" placeholder="Rejection reason">
                                                    <button class="btn btn-outline-danger btn-sm" type="submit" data-busy-label="Rejecting...">Reject</button>
                                                </form>
                                            </div>
                                        @else
                                            <span class="text-secondary small">Already processed</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $requests->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </section>
</main>
@endsection
