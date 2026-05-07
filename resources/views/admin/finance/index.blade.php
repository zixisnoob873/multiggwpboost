@extends('layouts.admin')

@section('title', 'GGWP Boost | Finance')

@php
    $snapshot = $incomeStatementSnapshot ?? [];
@endphp

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide">
    @include('admin.partials.page-header', [
        'title' => 'Finance',
        'subtitle' => 'Keep reporting, payout processing, and wallet controls grouped in one clean module.',
        'actions' => [
            ['label' => 'Withdrawals', 'href' => route('admin-withdrawal-requests.index', ['status' => 'pending']), 'class' => 'btn btn-danger btn-sm'],
            ['label' => 'Wallet Adjustments', 'href' => route('admin-wallet-adjustments.index')],
            ['label' => 'Income Statement', 'href' => route('admin-income-statement')],
        ],
    ])

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card app-card admin-stat-card h-100">
                <div class="card-body">
                    <div class="admin-stat-card__label">Pending Withdrawals</div>
                    <div class="admin-stat-card__value">{{ number_format($pendingWithdrawalsCount ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card app-card admin-stat-card h-100">
                <div class="card-body">
                    <div class="admin-stat-card__label">Revenue {{ $snapshot['selectedYear'] ?? now()->year }}</div>
                    <div class="admin-stat-card__value">${{ number_format(($snapshot['totalSaleCents'] ?? 0) / 100, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card app-card admin-stat-card h-100">
                <div class="card-body">
                    <div class="admin-stat-card__label">Payouts {{ $snapshot['selectedYear'] ?? now()->year }}</div>
                    <div class="admin-stat-card__value">${{ number_format(($snapshot['totalBoosterPayoutsCents'] ?? 0) / 100, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card app-card admin-stat-card h-100">
                <div class="card-body">
                    <div class="admin-stat-card__label">Platform Revenue</div>
                    <div class="admin-stat-card__value">${{ number_format(($snapshot['totalPlatformRevenueCents'] ?? 0) / 100, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-6">
            <section class="card app-card admin-section-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">Recent Withdrawals</h2>
                        <a class="btn btn-outline-light btn-sm" href="{{ route('admin-withdrawal-requests.index') }}">Open Queue</a>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Booster</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentWithdrawals as $withdrawal)
                                    <tr>
                                        <td class="fw-semibold">{{ $withdrawal->booster?->fullIdentity('Booster') }}</td>
                                        <td>${{ number_format(($withdrawal->amount_cents ?? 0) / 100, 2) }}</td>
                                        <td>{{ ucfirst($withdrawal->status) }}</td>
                                        <td>{{ $withdrawal->created_at?->format('M j, Y g:i A') ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4">
                                            @include('admin.partials.empty-state', [
                                                'title' => 'No recent withdrawals',
                                                'copy' => 'No booster payout requests match the current view.',
                                            ])
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <div class="col-xl-6">
            <section class="card app-card admin-section-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">Recent Wallet Adjustments</h2>
                        <a class="btn btn-outline-light btn-sm" href="{{ route('admin-wallet-adjustments.index') }}">Open Ledger</a>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Booster</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Admin</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentAdjustments as $adjustment)
                                    <tr>
                                        <td class="fw-semibold">{{ $adjustment->booster?->fullIdentity('Booster') }}</td>
                                        <td>{{ $adjustment->type === 'add' ? 'Credit' : 'Deduction' }}</td>
                                        <td>{{ $adjustment->type === 'add' ? '+' : '-' }}${{ number_format(($adjustment->amount_cents ?? 0) / 100, 2) }}</td>
                                        <td>{{ $adjustment->admin?->fullIdentity('Admin') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4">
                                            @include('admin.partials.empty-state', [
                                                'title' => 'No wallet adjustments',
                                                'copy' => 'Manual credits and deductions will show up here after they are recorded.',
                                            ])
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>
@endsection
