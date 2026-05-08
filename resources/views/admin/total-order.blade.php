@extends('layouts.admin')

@section('title', 'GGWP Boost | Orders')

@php
    use App\Support\OrderStatus;

    $tab = $orderFilters['tab'] ?? 'all';
    $sort = $orderFilters['sort'] ?? 'created_at';
    $direction = $orderFilters['direction'] ?? 'desc';
    $selectedCustomer = $customers->first(fn ($customer) => (string) $customer->id === (string) ($orderFilters['customer_id'] ?? ''));
    $selectedBooster = $boosters->first(fn ($booster) => (string) $booster->id === (string) ($orderFilters['booster_id'] ?? ''));
    $activeFilters = collect([
        ['label' => 'Search', 'value' => $orderFilters['search'] ?? null],
        ['label' => 'Status', 'value' => $orderFilters['status'] ?? null],
        ['label' => 'Payment', 'value' => $orderFilters['payment_status'] ?? null],
        ['label' => 'Assignment', 'value' => $orderFilters['assignment'] ?? null],
        ['label' => 'Customer', 'value' => $selectedCustomer ? trim($selectedCustomer->fullIdentity('Customer').' · '.$selectedCustomer->email) : null],
        ['label' => 'Booster', 'value' => $selectedBooster ? trim($selectedBooster->fullIdentity('Booster').' · '.$selectedBooster->email) : null],
        ['label' => 'Created From', 'value' => $orderFilters['created_from'] ?? null],
        ['label' => 'Created To', 'value' => $orderFilters['created_to'] ?? null],
    ])->filter(fn (array $filter): bool => filled($filter['value']));
    $tabLabels = [
        'all' => 'All',
        'needs_assignment' => 'Needs Assignment',
        'in_progress' => 'In Progress',
        'paused' => 'Paused',
        'completed' => 'Completed',
        'manual' => 'Manual',
    ];
    $sortUrl = function (string $field) use ($sort, $direction) {
        return request()->fullUrlWithQuery([
            'sort' => $field,
            'direction' => $sort === $field && $direction === 'asc' ? 'desc' : 'asc',
        ]);
    };
@endphp

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide admin-page admin-page--dense" data-order-filter-presets="ggwp-admin-order-presets">
    @include('admin.partials.page-header', [
        'title' => 'Orders',
        'subtitle' => 'Assignment, refund, and status queue.',
        'actions' => [
            ['label' => 'Manual Order', 'href' => route('admin-custom-order'), 'class' => 'btn btn-danger btn-sm'],
            ['label' => 'Export CSV', 'href' => route('admin-total-order.export', request()->query()), 'class' => 'btn btn-outline-light btn-sm'],
            ['label' => 'Chats', 'href' => route('admin-chats')],
        ],
    ])

    <div class="card app-card admin-filters-card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 mb-3">
                @foreach($tabLabels as $tabKey => $label)
                    <a class="btn {{ $tab === $tabKey ? 'btn-danger' : 'btn-outline-light' }} btn-sm" href="{{ route('admin-total-order', array_merge(request()->query(), ['tab' => $tabKey, 'page' => 1])) }}">
                        {{ $label }}
                        <span class="ms-1 text-secondary">{{ $orderTabs[$tabKey] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('admin-total-order') }}" class="row g-2" data-order-filter-form data-loading-form>
                <input type="hidden" name="tab" value="{{ $tab }}">

                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input class="form-control @error('search') is-invalid @enderror" name="search" value="{{ $orderFilters['search'] ?? '' }}" placeholder="Order, customer, booster, promo code">
                    @error('search')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select @error('status') is-invalid @enderror" name="status">
                        <option value="">All</option>
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}" {{ ($orderFilters['status'] ?? null) === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Payment</label>
                    <select class="form-select @error('payment_status') is-invalid @enderror" name="payment_status">
                        <option value="">All</option>
                        @foreach($paymentStatusOptions as $value => $label)
                            <option value="{{ $value }}" {{ ($orderFilters['payment_status'] ?? null) === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('payment_status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Assignment</label>
                    <select class="form-select @error('assignment') is-invalid @enderror" name="assignment">
                        <option value="any" {{ ($orderFilters['assignment'] ?? 'any') === 'any' ? 'selected' : '' }}>Any</option>
                        <option value="assigned" {{ ($orderFilters['assignment'] ?? null) === 'assigned' ? 'selected' : '' }}>Assigned</option>
                        <option value="unassigned" {{ ($orderFilters['assignment'] ?? null) === 'unassigned' ? 'selected' : '' }}>Unassigned</option>
                    </select>
                    @error('assignment')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Per Page</label>
                    <select class="form-select @error('per_page') is-invalid @enderror" name="per_page">
                        @foreach([10, 25, 50, 100] as $size)
                            <option value="{{ $size }}" {{ (int) ($orderFilters['per_page'] ?? 25) === $size ? 'selected' : '' }}>{{ $size }}</option>
                        @endforeach
                    </select>
                    @error('per_page')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Customer</label>
                    <div class="admin-searchable-select" data-searchable-select>
                        <input
                            class="form-control form-control-sm admin-searchable-select__input"
                            type="search"
                            value=""
                            placeholder="Search customers"
                            data-searchable-select-input
                            aria-label="Search customers"
                        >
                        <select class="form-select @error('customer_id') is-invalid @enderror" name="customer_id" data-searchable-select-target>
                        <option value="">All customers</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" {{ (string) ($orderFilters['customer_id'] ?? '') === (string) $customer->id ? 'selected' : '' }}>
                                {{ $customer->fullIdentity('Customer') }} · {{ $customer->email }}
                            </option>
                        @endforeach
                    </select>
                        <div class="form-text d-none" data-searchable-select-empty>No customers match this search.</div>
                    </div>
                    @error('customer_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Booster</label>
                    <div class="admin-searchable-select" data-searchable-select>
                        <input
                            class="form-control form-control-sm admin-searchable-select__input"
                            type="search"
                            value=""
                            placeholder="Search boosters"
                            data-searchable-select-input
                            aria-label="Search boosters"
                        >
                        <select class="form-select @error('booster_id') is-invalid @enderror" name="booster_id" data-searchable-select-target>
                        <option value="">All boosters</option>
                        @foreach($boosters as $booster)
                            <option value="{{ $booster->id }}" {{ (string) ($orderFilters['booster_id'] ?? '') === (string) $booster->id ? 'selected' : '' }}>
                                {{ $booster->fullIdentity('Booster') }} · {{ $booster->email }}
                            </option>
                        @endforeach
                    </select>
                        <div class="form-text d-none" data-searchable-select-empty>No boosters match this search.</div>
                    </div>
                    @error('booster_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Created From</label>
                    <input type="date" class="form-control @error('created_from') is-invalid @enderror" name="created_from" value="{{ $orderFilters['created_from'] ?? '' }}">
                    @error('created_from')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Created To</label>
                    <input type="date" class="form-control @error('created_to') is-invalid @enderror" name="created_to" value="{{ $orderFilters['created_to'] ?? '' }}">
                    @error('created_to')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sort</label>
                    <select class="form-select @error('sort') is-invalid @enderror" name="sort">
                        <option value="created_at" {{ $sort === 'created_at' ? 'selected' : '' }}>Created</option>
                        <option value="order_number" {{ $sort === 'order_number' ? 'selected' : '' }}>Order Number</option>
                        <option value="price_cents" {{ $sort === 'price_cents' ? 'selected' : '' }}>Amount</option>
                        <option value="status" {{ $sort === 'status' ? 'selected' : '' }}>Status</option>
                        <option value="assigned_at" {{ $sort === 'assigned_at' ? 'selected' : '' }}>Assigned</option>
                    </select>
                    @error('sort')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-danger" type="submit" data-busy-label="Applying...">Apply Filters</button>
                        <a class="btn btn-outline-light" href="{{ route('admin-total-order', ['tab' => $tab]) }}">Reset</a>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <input class="form-control form-control-sm admin-filter-field admin-filter-field--compact" data-order-preset-name placeholder="Save current filter set">
                        <button class="btn btn-outline-light btn-sm" type="button" data-save-order-preset>Save Preset</button>
                        <select class="form-select form-select-sm admin-filter-field admin-filter-field--compact" data-order-preset-select>
                            <option value="">Saved presets</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if($activeFilters->isNotEmpty())
        <div class="admin-chip-row mb-3">
            @foreach($activeFilters as $filter)
                <span class="admin-chip">{{ $filter['label'] }}: {{ $filter['value'] }}</span>
            @endforeach
        </div>
    @endif

    <section class="card app-card admin-section-card">
        <div class="card-body">
            @if($orders->isEmpty())
                @include('admin.partials.empty-state', [
                    'title' => 'No orders matched these filters',
                    'copy' => 'Try widening the date range, clearing a filter, or create a manual order if the work is still offline.',
                    'action' => ['label' => 'Reset Filters', 'href' => route('admin-total-order', ['tab' => $tab])],
                ])
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover align-middle mb-0 ggwp-data-table--wide">
                        <thead>
                            <tr>
                                <th><a class="text-decoration-none" href="{{ $sortUrl('order_number') }}">Order</a></th>
                                <th>Customer</th>
                                <th>Booster</th>
                                <th>Flags</th>
                                <th><a class="text-decoration-none" href="{{ $sortUrl('status') }}">Status</a></th>
                                <th><a class="text-decoration-none" href="{{ $sortUrl('price_cents') }}">Amount</a></th>
                                <th><a class="text-decoration-none" href="{{ $sortUrl('assigned_at') }}">Assigned</a></th>
                                <th><a class="text-decoration-none" href="{{ $sortUrl('created_at') }}">Created</a></th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($orders as $order)
                                @php
                                    $flags = collect([
                                        $order->is_custom ? 'Manual' : null,
                                        $order->hasPromoApplied() ? 'Promo' : null,
                                        $order->isExpedited() ? 'Urgent' : null,
                                        $order->isHighValue() ? 'High Value' : null,
                                    ])->filter()->values();
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">#{{ $order->order_number }}</div>
                                        <div class="small text-secondary">{{ $order->gameName() }} · {{ $order->serviceName() }}</div>
                                        <div class="small text-secondary">Add-ons: {{ $order->addonsLabel() }}</div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $order->user?->fullIdentity('Customer') }}</div>
                                        <div class="small text-secondary">{{ $order->user?->email }}</div>
                                    </td>
                                    <td class="admin-table-cell--assign">
                                        <form method="POST" action="{{ route('admin-orders.assign-booster', $order) }}" class="d-flex gap-2" data-loading-form>
                                            @csrf
                                            @method('PATCH')
                                            <select class="form-select form-select-sm" name="booster_id">
                                                <option value="">Unassigned</option>
                                                @foreach($boosters as $booster)
                                                    <option value="{{ $booster->id }}" {{ (int) $order->booster_id === (int) $booster->id ? 'selected' : '' }}>
                                                        {{ $booster->publicIdentity('Booster') }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <button class="btn btn-outline-light btn-sm" type="submit" data-busy-label="Saving...">
                                                {{ $order->booster_id ? 'Reassign' : 'Assign' }}
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <div class="admin-chip-row">
                                            @forelse($flags as $flag)
                                                <span class="admin-chip">{{ $flag }}</span>
                                            @empty
                                                <span class="text-secondary small">Standard</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td>
                                        @include('partials.order-status-badge', ['status' => $order->status])
                                        <div class="small text-secondary mt-1">{{ ucfirst((string) $order->payment_status) }}</div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">${{ number_format($order->customerPriceCents() / 100, 2) }}</div>
                                        @if($order->hasDiscountApplied())
                                            <div class="small text-secondary">Original ${{ number_format($order->resolvedOriginalPriceCents() / 100, 2) }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $order->assigned_at?->format('M j, Y') ?? 'Not assigned' }}</td>
                                    <td>{{ $order->created_at?->format('M j, Y g:i A') ?? '-' }}</td>
                                    <td class="text-end admin-table-cell--actions">
                                        <div class="ggwp-table-action-row ggwp-table-action-row--end">
                                            <a class="btn btn-outline-light btn-sm" href="{{ route('admin-orders.edit', $order) }}">Open</a>
                                            <a class="btn btn-outline-light btn-sm" href="{{ route('admin-chats.show', $order) }}">Chat</a>

                                            @if($order->canAdminPause())
                                                <form method="POST" action="{{ route('admin-orders.status', $order) }}" data-loading-form>
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="{{ OrderStatus::PAUSED }}">
                                                    <button class="btn btn-outline-warning btn-sm" type="submit" data-busy-label="Pausing...">Pause</button>
                                                </form>
                                            @endif

                                            @if($order->canAdminResume())
                                                <form method="POST" action="{{ route('admin-orders.status', $order) }}" data-loading-form>
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="{{ OrderStatus::IN_PROGRESS }}">
                                                    <button class="btn btn-outline-info btn-sm" type="submit" data-busy-label="Resuming...">Resume</button>
                                                </form>
                                            @endif

                                            @if($order->canAdminComplete())
                                                <form method="POST" action="{{ route('admin-orders.status', $order) }}" data-loading-form data-confirm-submit="Mark this order as completed?">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="{{ OrderStatus::COMPLETED }}">
                                                    <button class="btn btn-outline-success btn-sm" type="submit" data-busy-label="Completing...">Complete</button>
                                                </form>
                                            @endif

                                            @if($order->canAdminCancel())
                                                <form method="POST" action="{{ route('admin-orders.status', $order) }}" data-loading-form data-confirm-submit="Cancel this order?">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="{{ OrderStatus::CANCELLED }}">
                                                    <button class="btn btn-outline-danger btn-sm" type="submit" data-busy-label="Cancelling...">Cancel</button>
                                                </form>
                                            @endif

                                            @if($order->canAdminRefund())
                                                <form method="POST" action="{{ route('admin-orders.status', $order) }}" data-loading-form data-confirm-submit="Refund this order?">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="{{ OrderStatus::REFUNDED }}">
                                                    <button class="btn btn-outline-danger btn-sm" type="submit" data-busy-label="Refunding...">Refund</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mt-3">
                    <div class="text-secondary small">
                        Showing {{ $orders->firstItem() }}-{{ $orders->lastItem() }} of {{ number_format($orders->total()) }} orders
                    </div>
                    {{ $orders->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </section>
</main>
@endsection
