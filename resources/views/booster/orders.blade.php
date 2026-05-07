@php
    $currentView = $orderFilters['view'] ?? 'all';
    $resetUrl = route('booster-orders', ['view' => $currentView]);
@endphp

@extends('layouts.layout')

@section('title', 'GGWP Boost | Booster Orders')



@section('content')
<div class="ggwp-page-shell">
  <div class="ggwp-page-header mb-2">
    <div class="ggwp-page-header__copy">
      <span class="ggwp-page-eyebrow">Booster orders</span>
      <h1 class="h3 mb-1">My orders</h1>
      <div class="text-secondary">Search assigned work, update eligible statuses, and open active workspaces quickly.</div>
    </div>
    <div class="ggwp-page-actions">
      <a class="btn btn-outline-light" href="{{ route('booster-claim-orders') }}">Claim Orders</a>
      <a class="btn btn-outline-light" href="{{ route('booster-orders', ['view' => 'all']) }}">My Orders</a>
      <a class="btn btn-outline-light" href="{{ route('booster-chats') }}">Chats</a>
      <a class="btn btn-outline-light" href="{{ route('booster-wallet') }}">Wallet</a>
      <a class="btn btn-outline-light" href="{{ route('booster-dashboard') }}">Profile</a>
    </div>
  </div>

  @if(session('status'))
    <div class="alert alert-success mb-3">{{ session('status') }}</div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger mb-3">{{ $errors->first() }}</div>
  @endif

  <div class="card app-card ggwp-panel-card ggwp-panel-card--tight mb-3">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="btn-group btn-group-sm" role="group" aria-label="Order view">
          @foreach($orderViewOptions as $viewOption)
            <a
              class="btn {{ $currentView === $viewOption['value'] ? 'btn-danger' : 'btn-outline-light' }}"
              href="{{ route('booster-orders', array_filter(array_merge(request()->except('page'), ['view' => $viewOption['value']]))) }}"
            >
              {{ $viewOption['label'] }}
              <span class="ms-1">{{ $viewOption['count'] }}</span>
            </a>
          @endforeach
        </div>
        <div class="small text-secondary">{{ number_format($orders->total()) }} result{{ $orders->total() === 1 ? '' : 's' }}</div>
      </div>

      <form method="GET" action="{{ route('booster-orders') }}" class="row g-2 align-items-end ggwp-filter-grid" data-loading-form>
        <input type="hidden" name="view" value="{{ $currentView }}">

        <div class="col-lg-4">
          <label class="form-label" for="boosterOrdersSearch">Search</label>
          <input
            id="boosterOrdersSearch"
            class="form-control @error('search') is-invalid @enderror"
            name="search"
            value="{{ $orderFilters['search'] ?? '' }}"
            placeholder="Order, customer, or service"
          >
          @error('search')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>

        <div class="col-sm-4 col-lg-2">
          <label class="form-label" for="boosterOrdersStatus">Status</label>
          <select id="boosterOrdersStatus" class="form-select @error('status') is-invalid @enderror" name="status">
            <option value="">All</option>
            @foreach($orderFilterOptions['statuses'] as $value => $label)
              <option value="{{ $value }}" @selected(($orderFilters['status'] ?? null) === $value)>{{ $label }}</option>
            @endforeach
          </select>
          @error('status')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>

        <div class="col-sm-4 col-lg-2">
          <label class="form-label" for="boosterOrdersRegion">Region</label>
          <select id="boosterOrdersRegion" class="form-select @error('region') is-invalid @enderror" name="region">
            <option value="">All</option>
            @foreach($orderFilterOptions['regions'] as $value => $label)
              <option value="{{ $value }}" @selected(($orderFilters['region'] ?? null) === $value)>{{ $label }}</option>
            @endforeach
          </select>
          @error('region')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>

        <div class="col-sm-4 col-lg-2">
          <label class="form-label" for="boosterOrdersService">Service</label>
          <select id="boosterOrdersService" class="form-select @error('service') is-invalid @enderror" name="service">
            <option value="">All</option>
            @foreach($orderFilterOptions['services'] as $value => $label)
              <option value="{{ $value }}" @selected(($orderFilters['service'] ?? null) === $value)>{{ $label }}</option>
            @endforeach
          </select>
          @error('service')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>

        <div class="col-lg-2 d-flex gap-2">
          <button class="btn btn-danger flex-fill" type="submit" data-busy-label="Applying...">Apply</button>
          <a class="btn btn-outline-light flex-fill" href="{{ $resetUrl }}">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card app-card ggwp-panel-card ggwp-panel-card--tight">
    <div class="card-body">
      @if($orders->isEmpty())
        <div class="py-4 text-center">
          <h2 class="h5 mb-1">No orders found</h2>
          <p class="text-secondary mb-3">Clear filters or check the claim queue for new available assignments.</p>
          <div class="d-flex flex-wrap justify-content-center gap-2">
            <a class="btn btn-outline-light" href="{{ $resetUrl }}">Clear Filters</a>
            <a class="btn btn-outline-light" href="{{ route('booster-claim-orders') }}">Claim Orders</a>
          </div>
        </div>
      @else
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 ggwp-data-table ggwp-data-table--stacked">
            <thead>
              <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Service</th>
                <th>Task</th>
                <th>Region</th>
                <th>Est. payout</th>
                <th>Status</th>
                <th>Assigned</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($orders as $order)
                @php
                  $estimatedPayout = number_format(($order->resolvedBoosterPayoutCents() ?? 0) / 100, 2);
                  $canManageOrder = $order->canBoosterOpenWorkspace();
                  $statusOptions = \App\Support\OrderStatus::boosterUpdateOptionsFor($order->status);
                @endphp
                <tr>
                  <td class="fw-semibold">#{{ $order->order_number ?? $order->id }}</td>
                  <td>
                    <div>{{ $order->user?->publicIdentity('Unknown') ?? 'Unknown' }}</div>
                    <div class="text-secondary small">{{ $order->user?->email ?? '' }}</div>
                  </td>
                  <td>{{ $order->serviceName() }}</td>
                  <td>{{ $order->taskLabel() }}</td>
                  <td>{{ $order->regionLabel() }}</td>
                  <td>
                    <div>${{ $estimatedPayout }}</div>
                    <div class="small text-secondary">Basis ${{ number_format($order->resolvedBoosterPayoutBasisCents() / 100, 2) }}</div>
                  </td>
                  <td>@include('partials.order-status-badge', ['status' => $order->status])</td>
                  <td class="text-secondary small">{{ $order->assigned_at?->format('M j, Y H:i') ?? ($order->created_at?->format('M j, Y H:i') ?? '-') }}</td>
                  <td class="text-end">
                    <div class="ggwp-table-action-row">
                      @if($canManageOrder && count($statusOptions))
                        <form method="POST" action="{{ route('booster-orders.status', $order) }}" class="d-flex gap-2" data-loading-form>
                          @csrf
                          @method('PATCH')
                          <select name="status" class="form-select form-select-sm">
                            @foreach($statusOptions as $value => $label)
                              <option
                                value="{{ $value }}"
                                {{ ($order->status === $value || ($order->status === \App\Support\OrderStatus::PENDING && $value === \App\Support\OrderStatus::IN_PROGRESS)) ? 'selected' : '' }}
                              >
                                {{ $label }}
                              </option>
                            @endforeach
                          </select>
                          <button type="submit" class="btn btn-outline-light btn-sm" data-busy-label="Updating...">Update</button>
                        </form>
                      @endif

                      @if($canManageOrder)
                        <a class="btn btn-outline-light btn-sm" href="{{ route('booster-chats.show', ['order' => $order]) }}">Open</a>
                      @else
                        <span class="text-secondary small align-self-center">Locked</span>
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
  </div>
</div>
@endsection
