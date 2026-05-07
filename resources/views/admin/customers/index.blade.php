@extends('layouts.admin')

@section('title', 'GGWP Boost | Customers')

@php
    $sort = $customerFilters['sort'] ?? 'created_at';
    $direction = $customerFilters['direction'] ?? 'desc';
    $sortUrl = function (string $field) use ($sort, $direction) {
        return request()->fullUrlWithQuery([
            'sort' => $field,
            'direction' => $sort === $field && $direction === 'asc' ? 'desc' : 'asc',
        ]);
    };
@endphp

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide">
    @include('admin.partials.page-header', [
        'title' => 'Customers',
        'subtitle' => 'Unified customer directory with validated filters, pagination, and account actions.',
        'meta' => ['Total customers: '.number_format($customersCount ?? 0)],
        'actions' => [
            ['label' => 'Add Customer', 'href' => route('admin-customers.create'), 'class' => 'btn btn-danger btn-sm'],
            ['label' => 'Orders', 'href' => route('admin-total-order')],
        ],
    ])

    <form method="GET" action="{{ route('admin-customers.index') }}" class="card app-card admin-filters-card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input class="form-control @error('search') is-invalid @enderror" name="search" value="{{ $customerFilters['search'] ?? '' }}" placeholder="Name, nickname, or email">
                    @error('search')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select @error('status') is-invalid @enderror" name="status">
                        <option value="">All</option>
                        <option value="active" {{ ($customerFilters['status'] ?? null) === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="suspended" {{ ($customerFilters['status'] ?? null) === 'suspended' ? 'selected' : '' }}>Suspended</option>
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Created From</label>
                    <input type="date" class="form-control @error('created_from') is-invalid @enderror" name="created_from" value="{{ $customerFilters['created_from'] ?? '' }}">
                    @error('created_from')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Created To</label>
                    <input type="date" class="form-control @error('created_to') is-invalid @enderror" name="created_to" value="{{ $customerFilters['created_to'] ?? '' }}">
                    @error('created_to')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Per Page</label>
                    <select class="form-select @error('per_page') is-invalid @enderror" name="per_page">
                        @foreach([10, 20, 50, 100] as $size)
                            <option value="{{ $size }}" {{ (int) ($customerFilters['per_page'] ?? 20) === $size ? 'selected' : '' }}>{{ $size }}</option>
                        @endforeach
                    </select>
                    @error('per_page')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <button class="btn btn-danger" type="submit">Apply Filters</button>
                <a class="btn btn-outline-light" href="{{ route('admin-customers.index') }}">Reset</a>
            </div>
        </div>
    </form>

    <section class="card app-card admin-section-card">
        <div class="card-body">
            @if($customers->isEmpty())
                @include('admin.partials.empty-state', [
                    'title' => 'No customers matched these filters',
                    'copy' => 'Clear the search or date filters, or create a new customer account to start operations.',
                    'action' => ['label' => 'Add Customer', 'href' => route('admin-customers.create')],
                ])
            @else
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th><a class="text-decoration-none" href="{{ $sortUrl('nickname') }}">Customer</a></th>
                                <th><a class="text-decoration-none" href="{{ $sortUrl('email') }}">Email</a></th>
                                <th>Status</th>
                                <th><a class="text-decoration-none" href="{{ $sortUrl('orders_count') }}">Orders</a></th>
                                <th><a class="text-decoration-none" href="{{ $sortUrl('created_at') }}">Joined</a></th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($customers as $customer)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">
                                            <a class="link-light text-decoration-none" href="{{ route('admin-customers.show', $customer) }}">{{ $customer->fullIdentity('Customer') }}</a>
                                        </div>
                                        <div class="small text-secondary">{{ $customer->publicIdentity('Customer') }}</div>
                                    </td>
                                    <td>{{ $customer->email }}</td>
                                    <td>
                                        <span class="badge {{ $customer->account_status === 'suspended' ? 'text-bg-danger' : 'text-bg-success' }}">
                                            {{ ucfirst($customer->account_status ?? 'active') }}
                                        </span>
                                    </td>
                                    <td>{{ $customer->orders_count ?? 0 }}</td>
                                    <td>{{ $customer->created_at?->format('M j, Y g:i A') ?? '-' }}</td>
                                    <td class="text-end">
                                        <div class="d-flex flex-wrap justify-content-end gap-2">
                                            <form action="{{ route('admin-customers.status', $customer) }}" method="POST" data-loading-form>
                                                @csrf
                                                @method('PATCH')
                                                <button class="btn {{ $customer->account_status === 'suspended' ? 'btn-outline-success' : 'btn-outline-danger' }} btn-sm" type="submit" data-busy-label="Saving...">
                                                    {{ $customer->account_status === 'suspended' ? 'Activate' : 'Suspend' }}
                                                </button>
                                            </form>
                                            <a class="btn btn-outline-light btn-sm" href="{{ route('admin-customers.show', $customer) }}">View</a>
                                            <a class="btn btn-outline-light btn-sm" href="{{ route('admin-customers.edit', $customer) }}">Edit</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $customers->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </section>
</main>
@endsection
