@extends('layouts.admin')

@section('title', 'GGWP Boost | Edit Booster')



@php
    $displayName = $booster->fullIdentity('Booster');
    $nickname = $booster->publicIdentity('Booster');
    $currentBalanceCents = $walletSummary['current_balance_cents'] ?? 0;
    $availableBalanceCents = $walletSummary['available_balance_cents'] ?? 0;
    $pendingWithdrawalCents = $walletSummary['pending_withdrawal_cents'] ?? 0;
    $totalAdjustmentCents = $walletSummary['total_adjustment_cents'] ?? 0;
    $walletAdjustments = $walletSummary['wallet_adjustments'] ?? collect();
    $canManageFinance = auth()->user()?->canAccessAdminModule('finance');
@endphp

@push('head')
<style>
  .booster-edit-card,
  .booster-sidebar-card {
    overflow: hidden;
  }

  .booster-edit-card .card-body,
  .booster-sidebar-card .card-body {
    padding: 1.4rem;
  }

  .booster-card-intro {
    margin-bottom: 1.25rem;
  }

  .booster-card-eyebrow {
    display: inline-block;
    margin-bottom: 0.65rem;
    padding: 0.32rem 0.7rem;
    border: 1px solid rgba(255, 70, 85, 0.3);
    border-radius: 999px;
    background: rgba(255, 70, 85, 0.1);
    color: var(--ggwp-soft-text);
    font-size: 0.74rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }

  .booster-wallet-stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.85rem;
    margin-bottom: 1rem;
  }

  .booster-wallet-stat {
    min-height: 104px;
    padding: 1rem;
    border: 1px solid var(--ggwp-line);
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.03);
  }

  .booster-wallet-stat__label {
    display: block;
    margin-bottom: 0.35rem;
    color: var(--ggwp-muted);
    font-size: 0.77rem;
    font-weight: 600;
    letter-spacing: 0.05em;
    text-transform: uppercase;
  }

  .booster-wallet-stat__value {
    margin: 0;
    color: var(--ggwp-text);
    font-size: clamp(1.3rem, 2vw, 1.8rem);
    line-height: 1.1;
    word-break: break-word;
  }

  .booster-wallet-adjustment {
    padding-top: 1rem;
    margin-top: 1rem;
    border-top: 1px solid var(--ggwp-line);
  }

  .booster-wallet-adjustment-meta {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 0.75rem 1rem;
    margin-bottom: 1rem;
  }

  .booster-wallet-submit .btn {
    min-height: 46px;
  }

  .booster-history-wrap {
    padding-top: 1rem;
    margin-top: 1rem;
    border-top: 1px solid var(--ggwp-line);
  }

  .booster-history-table {
    margin-bottom: 0;
  }

  .booster-history-table th {
    font-size: 0.78rem;
    white-space: nowrap;
  }

  .booster-history-table td {
    font-size: 0.9rem;
    vertical-align: top;
  }

  .booster-history-date,
  .booster-history-admin {
    color: var(--ggwp-muted);
    font-size: 0.8rem;
    line-height: 1.35;
  }

  .booster-history-reason {
    min-width: 0;
    white-space: normal;
    word-break: break-word;
  }

  .booster-sidebar-stack {
    display: grid;
    gap: 0.9rem;
  }

  .booster-sidebar-box {
    padding: 0.95rem 1rem;
    border: 1px solid var(--ggwp-line);
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.03);
  }

  .booster-sidebar-actions {
    display: grid;
    gap: 0.85rem;
    padding-top: 0.25rem;
  }

  .booster-danger-zone {
    padding-top: 0.95rem;
    margin-top: 0.2rem;
    border-top: 1px solid var(--ggwp-line);
  }

  .booster-form-actions {
    padding-top: 0.35rem;
  }

  @media (max-width: 1399.98px) {
    .booster-wallet-stats {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 767.98px) {
    .booster-edit-card .card-body,
    .booster-sidebar-card .card-body {
      padding: 1.05rem;
    }

    .booster-wallet-adjustment-meta {
      margin-bottom: 0.85rem;
    }
  }
</style>
@endpush

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide">
  @include('admin.partials.page-header', [
    'title' => 'Edit Booster',
    'subtitle' => "Update account details and operational records for {$displayName} ({$nickname}).",
    'meta' => [
      'Status: '.ucfirst($booster->account_status ?? 'active'),
      'Assigned orders: '.number_format($booster->booster_orders_count ?? 0),
      $canManageFinance ? 'Finance access enabled' : 'Finance actions hidden',
    ],
    'actions' => array_values(array_filter([
      ['label' => 'View Profile', 'href' => route('admin-boosters.show', ['booster' => $booster->nickname])],
      ['label' => 'Back to Boosters', 'href' => route('admin-boosters.index')],
      $canManageFinance ? ['label' => 'Finance', 'href' => route('admin-withdrawal-requests.index')] : null,
    ])),
  ])

  @if(session('status'))
    <div class="alert alert-success mb-3" role="alert">{{ session('status') }}</div>
  @endif

  <div class="row g-4 align-items-start">
    <div class="col-12 col-xl-4">
      <section class="card app-card booster-edit-card h-100">
        <div class="card-body">
          <div class="booster-card-intro">
            <span class="booster-card-eyebrow">Account Details</span>
            <h2 class="h4 mb-1">Edit Booster</h2>
            <p class="text-secondary mb-0">Update the booster's profile, login email, password, and account status.</p>
          </div>

          <form action="{{ route('admin-boosters.update', ['booster' => $booster->nickname]) }}" method="POST" class="row g-3">
            @csrf
            @method('PATCH')
            @include('admin.boosters.partials.form-fields', [
              'booster' => $booster,
              'submitLabel' => 'Save Changes',
              'passwordLabel' => 'New password',
              'passwordRequired' => false,
              'passwordHelp' => 'Leave blank to keep the current password.',
              'cancelUrl' => route('admin-boosters.index'),
            ])
          </form>
        </div>
      </section>
    </div>

    <div class="col-12 col-xl-5">
      <section class="card app-card booster-edit-card">
        <div class="card-body">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 booster-card-intro">
            <div>
              <span class="booster-card-eyebrow">Booster Wallet</span>
              <h2 class="h4 mb-1">Wallet Balance</h2>
              <p class="text-secondary mb-0">Review the wallet summary, record a balance change, and inspect recent history.</p>
            </div>
            @if($canManageFinance)
              <a class="btn btn-outline-light" href="{{ route('admin-withdrawal-requests.index') }}">Open Finance Page</a>
            @endif
          </div>

          <div class="booster-wallet-stats">
            <div class="booster-wallet-stat">
              <span class="booster-wallet-stat__label">Current balance</span>
              <p class="booster-wallet-stat__value">${{ number_format($currentBalanceCents / 100, 2) }}</p>
            </div>
            <div class="booster-wallet-stat">
              <span class="booster-wallet-stat__label">Available now</span>
              <p class="booster-wallet-stat__value">${{ number_format($availableBalanceCents / 100, 2) }}</p>
            </div>
            <div class="booster-wallet-stat">
              <span class="booster-wallet-stat__label">Pending withdrawals</span>
              <p class="booster-wallet-stat__value">${{ number_format($pendingWithdrawalCents / 100, 2) }}</p>
            </div>
            <div class="booster-wallet-stat">
              <span class="booster-wallet-stat__label">Manual adjustments</span>
              <p class="booster-wallet-stat__value">
                {{ $totalAdjustmentCents >= 0 ? '+' : '-' }}${{ number_format(abs($totalAdjustmentCents) / 100, 2) }}
              </p>
            </div>
          </div>

          <div class="booster-wallet-adjustment">
            <div class="booster-wallet-adjustment-meta">
              <div>
                <h3 class="h6 mb-1">Adjust Balance</h3>
                <p class="text-secondary small mb-0">Use the action, amount, and reason fields below to record an admin change.</p>
              </div>
            </div>

            @if($canManageFinance)
              <form method="POST" action="{{ route('admin-wallet-adjustments.store') }}" class="row g-3">
                @csrf
                <input type="hidden" name="booster_id" value="{{ $booster->id }}">

                <div class="col-12 col-md-6">
                  <label class="form-label">Type</label>
                  <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                    <option value="deduct" {{ old('type', 'deduct') === 'deduct' ? 'selected' : '' }}>Deduct</option>
                    <option value="add" {{ old('type') === 'add' ? 'selected' : '' }}>Add</option>
                  </select>
                  @error('type')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                  @enderror
                </div>

                <div class="col-12 col-md-6">
                  <label class="form-label">Amount</label>
                  <input name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" placeholder="0.00" inputmode="decimal" required>
                  @error('amount')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                  @enderror
                </div>

                <div class="col-12">
                  <label class="form-label">Reason</label>
                  <input name="reason" class="form-control @error('reason') is-invalid @enderror" value="{{ old('reason') }}" placeholder="Why is this adjustment needed?" required>
                  @error('reason')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                  @enderror
                </div>

                <div class="col-12 col-md-6 col-lg-5 booster-wallet-submit">
                  <button class="btn btn-danger w-100" type="submit">Save Wallet Adjustment</button>
                </div>
              </form>
            @else
              <div class="alert alert-secondary mb-0">
                Finance actions are restricted to admins with finance access. You can still review this booster’s wallet history here.
              </div>
            @endif
          </div>

          <div class="booster-history-wrap">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
              <div>
                <h3 class="h6 mb-1">Recent Wallet History</h3>
                <p class="text-secondary small mb-0">Latest admin balance adjustments for this booster.</p>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-hover align-middle table-sm booster-history-table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Reason</th>
                    <th>Admin</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse($walletAdjustments->take(10) as $adjustment)
                    <tr>
                      <td class="booster-history-date">{{ $adjustment->created_at?->format('M j, Y H:i') ?? '-' }}</td>
                      <td>
                        <span class="badge {{ $adjustment->type === 'add' ? 'text-bg-success' : 'text-bg-danger' }}">
                          {{ $adjustment->type === 'add' ? 'Add' : 'Deduct' }}
                        </span>
                      </td>
                      <td class="fw-semibold">{{ $adjustment->type === 'add' ? '+' : '-' }}${{ number_format(($adjustment->amount_cents ?? 0) / 100, 2) }}</td>
                      <td class="booster-history-reason">{{ $adjustment->reason }}</td>
                      <td class="booster-history-admin">{{ $adjustment->admin?->name ?? 'Admin' }}</td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="5" class="text-center text-secondary py-4">No wallet adjustments recorded yet.</td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>
    </div>

    <div class="col-12 col-xl-3">
      @include('admin.boosters.partials.sidebar', ['booster' => $booster])
    </div>
  </div>
</main>
@endsection
