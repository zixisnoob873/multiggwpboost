@extends('layouts.layout')

@section('title', 'GGWP Boost | Wallet Adjustments')

@section('content')
<main class="ggwp-page-shell ggwp-page-shell--wide">
    <div class="ggwp-page-header mb-3">
        <div class="ggwp-page-header__copy">
            <h1 class="mb-0">Wallet Adjustments</h1>
            <p class="text-secondary mb-0">Dedicated finance workspace for manual balance changes with server-side wallet validation.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 ggwp-page-actions">
            <a class="btn btn-outline-light" href="{{ route('admin-finance.index') }}">Finance Overview</a>
            <a class="btn btn-outline-light" href="{{ route('admin-withdrawal-requests.index') }}">Withdrawals</a>
        </div>
    </div>

    @include('admin.partials.flash')

    <div class="row g-3">
        <div class="col-xl-4">
            <section class="card app-card ggwp-panel-card">
                <div class="card-body">
                    <h2 class="h4 mb-3">Record adjustment</h2>
                    <form method="POST" action="{{ route('admin-wallet-adjustments.store') }}" class="row g-3" data-validate-form data-dirty-form data-disable-on-submit>
                        @csrf
                        <div class="col-12">
                            <label class="form-label">Booster</label>
                            <select name="booster_id" id="walletAdjustmentBooster" class="form-select @error('booster_id') is-invalid @enderror" required>
                                <option value="">Select booster</option>
                                @foreach($boosters as $booster)
                                    <option value="{{ $booster->id }}" data-balance="{{ $boosterBalances[$booster->id] ?? 0 }}" @selected((string) old('booster_id') === (string) $booster->id)>{{ $booster->publicIdentity('Booster') }} - {{ $booster->email }}</option>
                                @endforeach
                            </select>
                            @error('booster_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Available balance: <strong id="selectedBoosterBalance">$0.00</strong></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Type</label>
                            <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                                <option value="deduct" @selected(old('type', 'deduct') === 'deduct')>Deduct</option>
                                <option value="add" @selected(old('type') === 'add')>Add</option>
                            </select>
                            @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Amount</label>
                            <input name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" inputmode="decimal" placeholder="0.00" required>
                            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Reason</label>
                            <input name="reason" class="form-control @error('reason') is-invalid @enderror" value="{{ old('reason') }}" maxlength="1000" placeholder="Explain the finance action" required>
                            @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <button class="btn btn-danger" type="submit" data-busy-label="Saving...">Save adjustment</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <div class="col-xl-8">
            <section class="card app-card ggwp-panel-card">
                <div class="card-body">
                    <h2 class="h4 mb-3">Adjustment history</h2>
                    @if($adjustments->count() === 0)
                        <div class="admin-empty-state">
                            <h3 class="h5 mb-2">No wallet adjustments yet</h3>
                            <p class="text-secondary mb-0">This page records manual finance actions that add to or deduct from booster wallet balances.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle mb-0 ggwp-data-table">
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
                                            <td>
                                                <div class="fw-semibold">{{ $adjustment->booster?->publicIdentity('Booster') ?? 'Booster' }}</div>
                                                <div class="small text-secondary">{{ $adjustment->booster?->email ?? '—' }}</div>
                                            </td>
                                            <td><span class="badge {{ $adjustment->type === 'add' ? 'text-bg-success' : 'text-bg-danger' }}">{{ $adjustment->type === 'add' ? 'Add' : 'Deduct' }}</span></td>
                                            <td>{{ $adjustment->type === 'add' ? '+' : '-' }}${{ number_format(($adjustment->amount_cents ?? 0) / 100, 2) }}</td>
                                            <td>{{ $adjustment->reason }}</td>
                                            <td>{{ $adjustment->admin?->name ?? 'Admin' }}</td>
                                            <td>{{ $adjustment->created_at?->format('M j, Y H:i') ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">{{ $adjustments->links('pagination::bootstrap-5') }}</div>
                    @endif
                </div>
            </section>
        </div>
    </div>
</main>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', () => {
  const boosterSelect = document.getElementById('walletAdjustmentBooster');
  const balanceText = document.getElementById('selectedBoosterBalance');

  if (!boosterSelect || !balanceText) {
    return;
  }

  const updateBalance = () => {
    const option = boosterSelect.options[boosterSelect.selectedIndex];
    const cents = Number(option?.dataset?.balance || 0);
    balanceText.textContent = `$${(cents / 100).toFixed(2)}`;
  };

  boosterSelect.addEventListener('change', updateBalance);
  updateBalance();
});
</script>
@endpush
