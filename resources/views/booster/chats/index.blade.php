@extends('layouts.layout')

@section('title', 'GGWP Boost | Booster Chats')



@section('content')
<div class="ggwp-page-shell">
  <div class="ggwp-page-header mb-2">
    <div class="ggwp-page-header__copy">
      <span class="ggwp-page-eyebrow">Booster communication</span>
      <h1 class="h3 mb-1">Chats</h1>
      <div class="text-secondary">Open customer and support conversations for assigned active orders.</div>
    </div>
    <div class="ggwp-page-actions">
      <a class="btn btn-outline-light" href="{{ route('booster-claim-orders') }}">Claim Orders</a>
      <a class="btn btn-outline-light" href="{{ route('booster-orders', ['view' => 'all']) }}">My Orders</a>
      <a class="btn btn-outline-light" href="{{ route('booster-wallet') }}">Wallet</a>
      <a class="btn btn-outline-light" href="{{ route('booster-dashboard') }}">Profile</a>
    </div>
  </div>

  @if($errors->any())
    <div class="alert alert-danger mb-3">{{ $errors->first() }}</div>
  @endif

  <div class="card app-card ggwp-panel-card ggwp-panel-card--tight mb-3">
    <div class="card-body">
      <form method="GET" action="{{ route('booster-chats') }}" class="row g-2 align-items-end ggwp-filter-grid" data-loading-form>
        <div class="col-lg-8">
          <label class="form-label" for="boosterChatSearch">Search</label>
          <input
            id="boosterChatSearch"
            class="form-control @error('search') is-invalid @enderror"
            name="search"
            value="{{ $chatFilters['search'] ?? '' }}"
            placeholder="Order, customer, or service"
          >
          @error('search')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>

        <div class="col-lg-2 d-grid">
          <button class="btn btn-danger" type="submit" data-busy-label="Applying...">Apply</button>
        </div>

        <div class="col-lg-2 d-grid">
          <a class="btn btn-outline-light" href="{{ route('booster-chats') }}">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card app-card ggwp-panel-card ggwp-panel-card--tight">
    <div class="card-body">
      @if($orders->isEmpty())
        <div class="py-4 text-center">
          <h2 class="h5 mb-1">No active chat orders</h2>
          <p class="text-secondary mb-3">Claim or start an assigned order to open its workspace conversations.</p>
          <div class="d-flex flex-wrap justify-content-center gap-2">
            <a class="btn btn-outline-light" href="{{ route('booster-claim-orders') }}">Claim Orders</a>
            <a class="btn btn-outline-light" href="{{ route('booster-orders', ['view' => 'all']) }}">My Orders</a>
          </div>
        </div>
      @else
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
          <h2 class="h5 mb-0">Active Orders</h2>
          <div class="small text-secondary">{{ number_format($orders->total()) }} result{{ $orders->total() === 1 ? '' : 's' }}</div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 ggwp-data-table ggwp-data-table--stacked">
            <thead>
              <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Service</th>
                <th>Task</th>
                <th>Status</th>
                <th>Assigned</th>
                <th class="text-end">Open</th>
              </tr>
            </thead>
            <tbody>
              @foreach($orders as $order)
                <tr>
                  <td class="fw-semibold">#{{ $order->order_number ?? $order->id }}</td>
                  <td>
                    <div>{{ $order->user?->publicIdentity('Unknown') ?? 'Unknown' }}</div>
                    <div class="text-secondary small">{{ $order->user?->email ?? '' }}</div>
                  </td>
                  <td>{{ $order->serviceName() }}</td>
                  <td>{{ $order->taskLabel() }}</td>
                  <td>@include('partials.order-status-badge', ['status' => $order->status])</td>
                  <td class="text-secondary small">{{ $order->assigned_at?->format('M j, Y H:i') ?? ($order->created_at?->format('M j, Y H:i') ?? '-') }}</td>
                  <td class="text-end">
                    <a class="btn btn-outline-light btn-sm" href="{{ route('booster-chats.show', ['order' => $order]) }}">Open</a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mt-3">
          <div class="text-secondary small">
            Showing {{ $orders->firstItem() }}-{{ $orders->lastItem() }} of {{ number_format($orders->total()) }} active orders
          </div>
          {{ $orders->links('pagination::bootstrap-5') }}
        </div>
      @endif
    </div>
  </div>
</div>
@endsection
