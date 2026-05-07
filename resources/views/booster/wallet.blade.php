@php
    use Illuminate\Support\Str;

    $currentBalance = number_format(($currentBalanceCents ?? 0) / 100, 2);
    $availableBalance = number_format(($availableBalanceCents ?? 0) / 100, 2);
    $totalEarned = number_format(($totalEarnedCents ?? 0) / 100, 2);
    $totalWithdrawn = number_format(($totalWithdrawnCents ?? 0) / 100, 2);
    $pendingEarnings = number_format(($pendingEarningsCents ?? 0) / 100, 2);
    $pendingWithdrawals = number_format(($pendingWithdrawalCents ?? 0) / 100, 2);
@endphp

@extends('layouts.layout')

@section('title', 'GGWP Boost | Wallet')



@section('content')
<div class="ggwp-page-shell">
  <div class="ggwp-page-header mb-2">
    <div class="ggwp-page-header__copy">
      <span class="ggwp-page-eyebrow">Booster wallet</span>
      <h1 class="h3 mb-1">Wallet</h1>
      @if($balanceSnapshotAt ?? null)
        <div class="small text-secondary mt-1">Live ledger at {{ $balanceSnapshotAt->format('M j, Y H:i:s') }}</div>
      @else
        <div class="text-secondary">Review balances, withdrawal requests, completed order payouts, and wallet adjustments.</div>
      @endif
    </div>
    <div class="ggwp-page-actions">
      <a class="btn btn-outline-light" href="{{ route('booster-claim-orders') }}">Claim Orders</a>
      <a class="btn btn-outline-light" href="{{ route('booster-orders', ['view' => 'all']) }}">My Orders</a>
      <a class="btn btn-outline-light" href="{{ route('booster-chats') }}">Chats</a>
      <a class="btn btn-outline-light" href="{{ route('booster-dashboard') }}">Profile</a>
    </div>
  </div>

  @if(session('status'))
    <div class="alert alert-success mb-3">{{ session('status') }}</div>
  @endif

  <div class="row g-2 mb-3">
    <div class="col-12 col-md-6 col-lg-3">
      <div class="card app-card h-100 ggwp-panel-card ggwp-panel-card--tight">
        <div class="card-body">
          <div class="text-secondary small">Current balance</div>
          <div class="h4 mb-0">${{ $currentBalance }}</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
      <div class="card app-card h-100 ggwp-panel-card ggwp-panel-card--tight">
        <div class="card-body">
          <div class="text-secondary small">Available now</div>
          <div class="h4 mb-0">${{ $availableBalance }}</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
      <div class="card app-card h-100 ggwp-panel-card ggwp-panel-card--tight">
        <div class="card-body">
          <div class="text-secondary small">Total earned</div>
          <div class="h4 mb-0">${{ $totalEarned }}</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
      <div class="card app-card h-100 ggwp-panel-card ggwp-panel-card--tight">
        <div class="card-body">
          <div class="text-secondary small">Pending earnings</div>
          <div class="h4 mb-0">${{ $pendingEarnings }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-5">
      <div class="card app-card h-100 ggwp-panel-card ggwp-panel-card--tight">
        <div class="card-body">
          <h2 class="h5 mb-3">Request Withdrawal</h2>

          <div class="d-flex justify-content-between mb-2">
            <span class="text-secondary">Pending withdrawals</span>
            <strong>${{ $pendingWithdrawals }}</strong>
          </div>
          <div class="d-flex justify-content-between mb-3">
            <span class="text-secondary">Total withdrawn</span>
            <strong>${{ $totalWithdrawn }}</strong>
          </div>

          <form method="POST" action="{{ route('booster-wallet.withdraw') }}" data-loading-form data-validate-form novalidate>
            @csrf
            <label class="form-label" for="withdrawalAmount">Amount</label>
            <div class="input-group">
              <span class="input-group-text">$</span>
              <input
                id="withdrawalAmount"
                name="amount"
                type="number"
                min="10"
                step="0.01"
                inputmode="decimal"
                class="form-control @error('amount') is-invalid @enderror"
                placeholder="0.00"
                value="{{ old('amount') }}"
                required
              >
            </div>
            @error('amount')
              <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror

            <button class="btn btn-danger w-100 mt-3" type="submit" data-busy-label="Submitting...">Submit Withdrawal Request</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-7">
      <div class="card app-card ggwp-panel-card ggwp-panel-card--tight">
        <div class="card-body">
          <h2 class="h5 mb-3">Completed Orders</h2>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 ggwp-data-table ggwp-data-table--stacked">
              <thead>
                <tr>
                  <th>Order ID</th>
                  <th>Service</th>
                  <th>Completed</th>
                  <th class="text-end">Payout</th>
                </tr>
              </thead>
              <tbody>
                @forelse($completedOrders as $order)
                  @php($payout = number_format(($order->resolvedBoosterPayoutCents() ?? 0) / 100, 2))
                  <tr>
                    <td class="fw-semibold">#{{ $order->order_number ?? $order->id }}</td>
                    <td>{{ $order->serviceName() }}</td>
                    <td>{{ $order->updated_at?->format('M j, Y') ?? ($order->created_at?->format('M j, Y') ?? '-') }}</td>
                    <td class="text-end">
                      <div>${{ $payout }}</div>
                      <div class="small text-secondary">Basis ${{ number_format($order->resolvedBoosterPayoutBasisCents() / 100, 2) }}</div>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="4" class="text-secondary text-center py-4 ggwp-table-empty">No completed orders yet.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card app-card ggwp-panel-card ggwp-panel-card--tight mt-3">
    <div class="card-body">
      <h2 class="h5 mb-3">Withdrawal Requests</h2>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 ggwp-data-table ggwp-data-table--stacked">
          <thead>
            <tr>
              <th>Requested</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Reason</th>
              <th>Processed</th>
            </tr>
          </thead>
          <tbody>
            @forelse($withdrawalRequests as $request)
              @php
                $statusText = Str::title($request->status ?? 'pending');
                $badgeClass = match ($request->status) {
                    'approved', 'paid' => 'text-bg-success',
                    'rejected' => 'text-bg-danger',
                    default => 'text-bg-warning',
                };
              @endphp
              <tr>
                <td>{{ $request->created_at?->format('M j, Y H:i') ?? '-' }}</td>
                <td>${{ number_format(($request->amount_cents ?? 0) / 100, 2) }}</td>
                <td><span class="badge {{ $badgeClass }}">{{ $statusText }}</span></td>
                <td>{{ $request->notes ?: '-' }}</td>
                <td>{{ $request->processed_at?->format('M j, Y H:i') ?? '-' }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-center text-secondary py-4 ggwp-table-empty">No withdrawal requests yet.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card app-card ggwp-panel-card ggwp-panel-card--tight mt-3">
    <div class="card-body">
      <h2 class="h5 mb-3">Wallet Adjustments</h2>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 ggwp-data-table ggwp-data-table--stacked">
          <thead>
            <tr>
              <th>Date</th>
              <th>Amount</th>
              <th>Reason</th>
              <th>Admin</th>
            </tr>
          </thead>
          <tbody>
            @forelse($walletAdjustments as $adjustment)
              <tr>
                <td>{{ $adjustment->created_at?->format('M j, Y H:i') ?? '-' }}</td>
                <td>{{ $adjustment->type === 'add' ? '+' : '-' }}${{ number_format(($adjustment->amount_cents ?? 0) / 100, 2) }}</td>
                <td>{{ $adjustment->reason }}</td>
                <td>{{ $adjustment->admin?->name ?? 'Admin' }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="text-center text-secondary py-4 ggwp-table-empty">No wallet adjustments recorded.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
