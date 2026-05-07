@php
    use Illuminate\Support\Str;

    $displayName = $user->publicIdentity('Customer');
    $userInitials = collect(explode(' ', $displayName))
        ->filter()
        ->take(2)
        ->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))
        ->implode('');
    $lifetimeSpend = number_format(($lifetimeSpendCents ?? 0) / 100, 2);
@endphp

@extends('layouts.layout')

@section('title', 'GGWP Boost | Customer Dashboard')



@section('content')
<div class="ggwp-page-shell">
  @if(session('status'))
    <div class="alert alert-success mb-3">{{ session('status') }}</div>
  @endif

  <div class="ggwp-page-header mb-2">
    <div class="ggwp-page-header__copy">
      <span class="ggwp-page-eyebrow">Customer dashboard</span>
      <h1 class="h3 mb-1">{{ $displayName }}</h1>
      <div class="text-secondary">Track active boosts, order history, account status, and profile security.</div>
    </div>
    <div class="ggwp-page-actions">
      <a class="btn btn-danger" href="{{ route('home') }}">Start Boost</a>
    </div>
  </div>

  <div class="row g-2 ggwp-metric-grid mb-3">
    <div class="col-md-6 col-xl-3">
      <div class="card app-card h-100 ggwp-panel-card ggwp-panel-card--tight">
        <div class="card-body">
          <p class="text-secondary small mb-1">Total orders</p>
          <div class="h3 mb-0">{{ $totalOrders ?? 0 }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3">
      <div class="card app-card h-100 ggwp-panel-card ggwp-panel-card--tight">
        <div class="card-body">
          <p class="text-secondary small mb-1">Active now</p>
          <div class="h3 mb-0">{{ $activeOrdersCount ?? 0 }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3">
      <div class="card app-card h-100 ggwp-panel-card ggwp-panel-card--tight">
        <div class="card-body">
          <p class="text-secondary small mb-1">Completed</p>
          <div class="h3 mb-0">{{ $completedOrdersCount ?? 0 }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3">
      <div class="card app-card h-100 ggwp-panel-card ggwp-panel-card--tight">
        <div class="card-body">
          <p class="text-secondary small mb-1">Lifetime spend</p>
          <div class="h3 mb-0">${{ $lifetimeSpend }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card app-card ggwp-panel-card ggwp-panel-card--tight mb-3">
        <div class="card-body">
          <div class="d-flex align-items-center gap-3 mb-3">
            @if($user->profile_photo_url)
              <img class="ggwp-avatar-orb ggwp-avatar-photo" src="{{ $user->profile_photo_url }}" alt="{{ $displayName }} profile picture" decoding="async">
            @else
              <div class="ggwp-avatar-orb">
                {{ $userInitials !== '' ? $userInitials : 'U' }}
              </div>
            @endif
            <div class="min-w-0">
              <h2 class="h5 mb-1 text-truncate">{{ $displayName }}</h2>
              <p class="text-secondary mb-0 text-truncate">{{ $user->email }}</p>
            </div>
          </div>

          <div class="d-flex justify-content-between mb-2">
            <span class="text-secondary">Status</span>
            <span class="badge {{ ($user->account_status ?? 'active') === 'suspended' ? 'text-bg-danger' : 'text-bg-success' }}">
              {{ Str::title($user->account_status ?? 'active') }}
            </span>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-secondary">Role</span>
            <span>{{ Str::title(str_replace('-', ' ', $user->role ?? 'customer')) }}</span>
          </div>
          <div class="d-flex justify-content-between mb-3">
            <span class="text-secondary">Joined</span>
            <span>{{ $user->created_at?->format('M j, Y') ?? '-' }}</span>
          </div>

          <form
            method="POST"
            action="{{ route('user.profile-photo.update') }}"
            enctype="multipart/form-data"
            class="ggwp-profile-upload"
            data-auto-upload-form
            data-max-bytes="4194304"
            data-validate-form
            novalidate
          >
            @csrf
            <label class="form-label" for="customerProfilePhoto">Profile picture</label>
            <input
              id="customerProfilePhoto"
              type="file"
              name="profile_photo"
              class="visually-hidden @error('profile_photo') is-invalid @enderror"
              accept="image/png,image/jpeg,image/webp"
              data-file-input
            >
            <button type="button" class="btn btn-outline-light w-100" data-file-trigger>Upload Picture</button>
            @error('profile_photo')
              <div class="invalid-feedback d-block" data-file-feedback>{{ $message }}</div>
            @else
              <div class="invalid-feedback d-none" data-file-feedback></div>
            @enderror
          </form>
        </div>
      </div>

      <div class="card app-card ggwp-panel-card ggwp-panel-card--tight">
        <div class="card-body">
          <h2 class="h5 mb-3">Change Password</h2>
          <form method="POST" action="{{ route('user.password.update') }}" data-loading-form data-validate-form novalidate>
            @csrf

            <div class="mb-3">
              <label class="form-label" for="customerCurrentPassword">Current Password</label>
              <input id="customerCurrentPassword" type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" autocomplete="current-password" required>
              @error('current_password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label class="form-label" for="customerNewPassword">New Password</label>
              <input id="customerNewPassword" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" required>
              @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label class="form-label" for="customerNewPasswordConfirmation">Confirm New Password</label>
              <input id="customerNewPasswordConfirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password" required>
            </div>

            <button type="submit" class="btn btn-outline-light w-100" data-busy-label="Updating...">Update Password</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="card app-card ggwp-panel-card ggwp-panel-card--tight mb-3">
        <div class="card-body">
          <div class="ggwp-section-header">
            <div>
              <h2 class="h5 mb-0">Active orders</h2>
            </div>
            <a class="btn btn-outline-light btn-sm" href="{{ route('allorders') }}">See all</a>
          </div>

          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 ggwp-data-table ggwp-data-table--stacked">
              <thead>
                <tr>
                  <th>Order</th>
                  <th>Service</th>
                  <th>Status</th>
                  <th>Booster</th>
                  <th class="text-end">Open</th>
                </tr>
              </thead>
              <tbody>
                @forelse($activeOrders as $order)
                  <tr>
                    <td class="fw-semibold">#{{ $order->order_number ?? $order->id }}</td>
                    <td>{{ $order->serviceName() }}</td>
                    <td>@include('partials.order-status-badge', ['status' => $order->status])</td>
                    <td>{{ $order->booster?->publicIdentity('Unassigned') ?? 'Unassigned' }}</td>
                    <td class="text-end">
                      <a class="btn btn-outline-light btn-sm" href="{{ route('user-chats.show', ['order' => $order]) }}">Open</a>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="text-center text-secondary py-4 ggwp-table-empty">No active orders right now. Start a boost when you are ready.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card app-card ggwp-panel-card ggwp-panel-card--tight">
        <div class="card-body">
          <div class="ggwp-section-header">
            <div>
              <h2 class="h5 mb-0">Recent orders</h2>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 ggwp-data-table ggwp-data-table--stacked">
              <thead>
                <tr>
                  <th>Order</th>
                  <th>Date</th>
                  <th>Service</th>
                  <th>Charged Total</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                @forelse($recentOrders as $order)
                  <tr>
                    <td class="fw-semibold">#{{ $order->order_number ?? $order->id }}</td>
                    <td>{{ $order->created_at?->format('M j, Y') ?? '-' }}</td>
                    <td>{{ $order->serviceName() }}</td>
                    <td>
                      <div>${{ number_format($order->customerPriceCents() / 100, 2) }}</div>
                      @if($order->hasDiscountApplied())
                        <div class="small text-secondary">Original ${{ number_format($order->resolvedOriginalPriceCents() / 100, 2) }}</div>
                        <div class="small text-secondary">Promo -${{ number_format($order->resolvedDiscountAmountCents() / 100, 2) }}</div>
                      @endif
                    </td>
                    <td>@include('partials.order-status-badge', ['status' => $order->status])</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="text-center text-secondary py-4 ggwp-table-empty">You have not placed any orders yet.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
