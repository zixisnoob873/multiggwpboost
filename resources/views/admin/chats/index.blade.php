@extends('layouts.admin')

@section('title', 'GGWP Boost | Chats')

@php
    use App\Enums\OrderChatThreadType;
    use App\Models\User;
@endphp

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide admin-page admin-page--dense">
    @include('admin.partials.page-header', [
        'title' => 'Chats',
        'subtitle' => 'Reply and triage.',
        'actions' => [
            ['label' => 'Orders', 'href' => route('admin-total-order')],
            ['label' => 'Manual Orders', 'href' => route('admin-custom-order')],
        ],
    ])

    <div class="row g-2 mb-2">
        <div class="col-md-4">
            <div class="card app-card admin-stat-card h-100">
                <div class="card-body">
                    <div class="admin-stat-card__label">Active Chat Orders</div>
                    <div class="admin-stat-card__value">{{ number_format($chatStats['all'] ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card app-card admin-stat-card h-100">
                <div class="card-body">
                    <div class="admin-stat-card__label">Needs Reply</div>
                    <div class="admin-stat-card__value">{{ number_format($chatStats['needs_reply'] ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card app-card admin-stat-card h-100">
                <div class="card-body">
                    <div class="admin-stat-card__label">Stale 12h+</div>
                    <div class="admin-stat-card__value">{{ number_format($chatStats['stale'] ?? 0) }}</div>
                </div>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('admin-chats') }}" class="card app-card admin-filters-card mb-3" data-loading-form>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input class="form-control @error('search') is-invalid @enderror" name="search" value="{{ $chatFilters['search'] ?? '' }}" placeholder="Order id, customer, booster">
                    @error('search')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Reply State</label>
                    <select class="form-select @error('reply_state') is-invalid @enderror" name="reply_state">
                        <option value="all" {{ ($chatFilters['reply_state'] ?? 'all') === 'all' ? 'selected' : '' }}>All</option>
                        <option value="needs_reply" {{ ($chatFilters['reply_state'] ?? null) === 'needs_reply' ? 'selected' : '' }}>Needs Reply</option>
                        <option value="stale" {{ ($chatFilters['reply_state'] ?? null) === 'stale' ? 'selected' : '' }}>Stale</option>
                    </select>
                    @error('reply_state')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Order Status</label>
                    <select class="form-select @error('status') is-invalid @enderror" name="status">
                        <option value="">Any</option>
                        @foreach(App\Support\OrderStatus::options() as $value => $label)
                            <option value="{{ $value }}" {{ ($chatFilters['status'] ?? null) === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Lane</label>
                    <select class="form-select @error('lane') is-invalid @enderror" name="lane">
                        <option value="">Any</option>
                        @foreach(OrderChatThreadType::values() as $lane)
                            <option value="{{ $lane }}" {{ ($chatFilters['lane'] ?? null) === $lane ? 'selected' : '' }}>
                                {{ OrderChatThreadType::from($lane)->participantsLabel() }}
                            </option>
                        @endforeach
                    </select>
                    @error('lane')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Per Page</label>
                    <select class="form-select @error('per_page') is-invalid @enderror" name="per_page">
                        @foreach([10, 18, 36, 72] as $size)
                            <option value="{{ $size }}" {{ (int) ($chatFilters['per_page'] ?? 18) === $size ? 'selected' : '' }}>{{ $size }}</option>
                        @endforeach
                    </select>
                    @error('per_page')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <button class="btn btn-danger" type="submit" data-busy-label="Applying...">Apply Filters</button>
                <a class="btn btn-outline-light" href="{{ route('admin-chats') }}">Reset</a>
            </div>
        </div>
    </form>

    <section class="card app-card admin-section-card">
        <div class="card-body">
            @if($orders->isEmpty())
                @include('admin.partials.empty-state', [
                    'title' => 'No chats matched these filters',
                    'copy' => 'This page only lists orders that already have message activity. Clear a filter or jump in from an order row after the first message lands.',
                    'action' => ['label' => 'Open Orders', 'href' => route('admin-total-order')],
                ])
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Booster</th>
                                <th>Lane</th>
                                <th>Status</th>
                                <th>Last Activity</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($orders as $order)
                                @php
                                    $laneLabel = $order->latest_chat_thread_type
                                        ? OrderChatThreadType::from($order->latest_chat_thread_type)->participantsLabel()
                                        : 'Chat';
                                    $needsReply = $order->latest_sender_role !== User::ROLE_SUPER_ADMIN;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">#{{ $order->order_number }}</div>
                                        <div class="small text-secondary">{{ $order->serviceName() }}</div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $order->user?->fullIdentity('Customer') }}</div>
                                        <div class="small text-secondary">{{ $order->user?->email }}</div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $order->booster?->fullIdentity('Unassigned') ?? 'Unassigned' }}</div>
                                        <div class="small text-secondary">{{ $order->booster?->email ?? 'No booster assigned' }}</div>
                                    </td>
                                    <td>
                                        <div>{{ $laneLabel }}</div>
                                        @if($needsReply)
                                            <div class="small text-warning">Needs admin reply</div>
                                        @endif
                                    </td>
                                    <td>@include('partials.order-status-badge', ['status' => $order->status])</td>
                                    <td>{{ $order->latest_chat_at ? \Illuminate\Support\Carbon::parse($order->latest_chat_at)->diffForHumans() : '-' }}</td>
                                    <td class="text-end">
                                        <div class="d-flex flex-wrap justify-content-end gap-2">
                                            <a class="btn btn-outline-light btn-sm" href="{{ route('admin-orders.edit', $order) }}">Order</a>
                                            <a class="btn btn-danger btn-sm" href="{{ route('admin-chats.show', $order) }}">Open Chat</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mt-3">
                    <div class="text-secondary small">
                        Showing {{ $orders->firstItem() }}-{{ $orders->lastItem() }} of {{ number_format($orders->total()) }} active chat orders
                    </div>
                    {{ $orders->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </section>
</main>
@endsection
