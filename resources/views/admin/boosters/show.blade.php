@extends('layouts.admin')

@section('title', 'GGWP Boost | Booster Profile')

@section('admin_content')
@php
    $walletCurrent = (int) ($walletSummary['current_balance_cents'] ?? 0);
    $walletAvailable = (int) ($walletSummary['available_balance_cents'] ?? 0);
    $walletPending = (int) ($walletSummary['pending_withdrawal_cents'] ?? 0);
    $walletAdjusted = (int) ($walletSummary['total_adjustment_cents'] ?? 0);
@endphp
<main class="ggwp-page-shell ggwp-page-shell--wide admin-page admin-page--dense">
    @include('admin.partials.page-header', [
        'title' => 'Booster Profile',
        'subtitle' => $booster->fullIdentity('Booster').' · '.$booster->email,
        'meta' => [
            'Nickname: '.$booster->publicIdentity('Booster'),
            'Status: '.ucfirst($booster->account_status ?? 'active'),
            'Joined: '.($booster->created_at?->format('M j, Y g:i A') ?? '-'),
        ],
        'actions' => array_values(array_filter([
            ['label' => 'Edit', 'href' => route('admin-boosters.edit', ['booster' => $booster->nickname]), 'class' => 'btn btn-danger btn-sm'],
            auth()->user()?->canAccessAdminModule('finance') ? ['label' => 'Finance', 'href' => route('admin-withdrawal-requests.index')] : null,
            ['label' => 'Back', 'href' => route('admin-boosters.index')],
        ])),
    ])

    <section class="admin-stat-grid">
        <article class="card app-card admin-stat-card">
            <div class="card-body">
                <div class="admin-stat-card__label">Assigned Orders</div>
                <div class="admin-stat-card__value">{{ number_format($orderStats['total'] ?? 0) }}</div>
            </div>
        </article>
        <article class="card app-card admin-stat-card">
            <div class="card-body">
                <div class="admin-stat-card__label">Active Orders</div>
                <div class="admin-stat-card__value">{{ number_format($orderStats['active'] ?? 0) }}</div>
            </div>
        </article>
        <article class="card app-card admin-stat-card">
            <div class="card-body">
                <div class="admin-stat-card__label">Current Wallet</div>
                <div class="admin-stat-card__value">${{ number_format($walletCurrent / 100, 2) }}</div>
            </div>
        </article>
        <article class="card app-card admin-stat-card">
            <div class="card-body">
                <div class="admin-stat-card__label">Tracked Payout</div>
                <div class="admin-stat-card__value">${{ number_format(((int) ($orderStats['paid_out_cents'] ?? 0)) / 100, 2) }}</div>
            </div>
        </article>
    </section>

    <div class="row g-2">
        <div class="col-xl-4">
            <section class="card app-card admin-section-card mb-2">
                <div class="card-body">
                    <h2 class="h5 mb-3">Account Details</h2>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-5 text-secondary">Full name</dt>
                        <dd class="col-sm-7">{{ $booster->fullIdentity('Booster') }}</dd>
                        <dt class="col-sm-5 text-secondary">Nickname</dt>
                        <dd class="col-sm-7">{{ $booster->publicIdentity('Booster') }}</dd>
                        <dt class="col-sm-5 text-secondary">Email</dt>
                        <dd class="col-sm-7">{{ $booster->email }}</dd>
                        <dt class="col-sm-5 text-secondary">Role</dt>
                        <dd class="col-sm-7 text-capitalize">{{ $booster->role }}</dd>
                        <dt class="col-sm-5 text-secondary">Status</dt>
                        <dd class="col-sm-7">
                            <span class="badge {{ $booster->account_status === 'suspended' ? 'text-bg-danger' : 'text-bg-success' }}">
                                {{ ucfirst($booster->account_status ?? 'active') }}
                            </span>
                        </dd>
                        <dt class="col-sm-5 text-secondary">Created</dt>
                        <dd class="col-sm-7">{{ $booster->created_at?->format('M j, Y g:i A') ?? '-' }}</dd>
                        <dt class="col-sm-5 text-secondary">Updated</dt>
                        <dd class="col-sm-7">{{ $booster->updated_at?->format('M j, Y g:i A') ?? '-' }}</dd>
                    </dl>

                    <div class="d-grid gap-2 mt-3">
                        <a class="btn btn-outline-light" href="{{ route('admin-boosters.edit', ['booster' => $booster->nickname]) }}">Edit account</a>
                        <form action="{{ route('admin-boosters.status', $booster) }}" method="POST" data-loading-form>
                            @csrf
                            @method('PATCH')
                            <button class="btn {{ $booster->account_status === 'suspended' ? 'btn-success' : 'btn-warning' }} w-100" type="submit" data-busy-label="Saving...">
                                {{ $booster->account_status === 'suspended' ? 'Activate Booster' : 'Suspend Booster' }}
                            </button>
                        </form>
                    </div>
                </div>
            </section>

            <section class="card app-card admin-section-card">
                <div class="card-body">
                    <h2 class="h5 mb-3">Wallet &amp; Withdrawals</h2>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-6 text-secondary">Current balance</dt>
                        <dd class="col-sm-6">${{ number_format($walletCurrent / 100, 2) }}</dd>
                        <dt class="col-sm-6 text-secondary">Available now</dt>
                        <dd class="col-sm-6">${{ number_format($walletAvailable / 100, 2) }}</dd>
                        <dt class="col-sm-6 text-secondary">Pending withdrawals</dt>
                        <dd class="col-sm-6">${{ number_format($walletPending / 100, 2) }}</dd>
                        <dt class="col-sm-6 text-secondary">Manual adjustments</dt>
                        <dd class="col-sm-6">{{ $walletAdjusted >= 0 ? '+' : '-' }}${{ number_format(abs($walletAdjusted) / 100, 2) }}</dd>
                    </dl>
                    @if(auth()->user()?->canAccessAdminModule('finance'))
                        <div class="d-grid gap-2 mt-3">
                            <a class="btn btn-outline-light" href="{{ route('admin-withdrawal-requests.index') }}">Open withdrawals</a>
                            <a class="btn btn-outline-light" href="{{ route('admin-wallet-adjustments.index') }}">Open adjustments</a>
                        </div>
                    @endif
                </div>
            </section>
        </div>

        <div class="col-xl-8">
            <section class="card app-card admin-section-card mb-2">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                        <h2 class="h5 mb-0">Recent Orders</h2>
                        <a class="btn btn-outline-light btn-sm" href="{{ route('admin-total-order', ['search' => $booster->nickname]) }}">Open filtered orders</a>
                    </div>

                    @if($recentOrders->isEmpty())
                        @include('admin.partials.empty-state', [
                            'title' => 'No assigned orders yet',
                            'copy' => 'This booster does not have any linked orders yet.',
                        ])
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Customer</th>
                                        <th>Status</th>
                                        <th>Payout</th>
                                        <th>Assigned</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentOrders as $order)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">#{{ $order->order_number }}</div>
                                                <div class="small text-secondary">{{ $order->serviceName() }}</div>
                                            </td>
                                            <td>{{ $order->user?->publicIdentity('Customer') ?? 'Customer' }}</td>
                                            <td><span class="badge {{ $order->statusBadgeClass() }}">{{ $order->statusLabel() }}</span></td>
                                            <td>${{ number_format($order->resolvedBoosterPayoutCents() / 100, 2) }}</td>
                                            <td>{{ $order->assigned_at?->format('M j, Y') ?? '-' }}</td>
                                            <td class="text-end">
                                                <a class="btn btn-outline-light btn-sm" href="{{ route('admin-orders.edit', $order) }}">Open</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </section>

            <div class="row g-2">
                <div class="col-lg-6">
                    <section class="card app-card admin-section-card h-100">
                        <div class="card-body">
                            <h2 class="h5 mb-3">Application & Finance Context</h2>
                            <div class="d-grid gap-3">
                                <div class="border rounded-3 p-3">
                                    <div class="fw-semibold mb-1">Booster application</div>
                                    @if($sourceApplication)
                                        <div class="small text-secondary">Status: {{ ucfirst($sourceApplication->status ?? 'new') }}</div>
                                        <div class="small text-secondary">Reviewed by: {{ $sourceApplication->reviewer?->fullIdentity('Admin') ?? 'Not reviewed' }}</div>
                                        <div class="small text-secondary">Converted: {{ $sourceApplication->converted_at?->format('M j, Y g:i A') ?? 'Not converted' }}</div>
                                        <a class="btn btn-outline-light btn-sm mt-2" href="{{ route('admin-booster-applications.edit', $sourceApplication) }}">Open application</a>
                                    @else
                                        <div class="text-secondary small">No linked booster application was found for this booster.</div>
                                    @endif
                                </div>

                                <div class="border rounded-3 p-3">
                                    <div class="fw-semibold mb-2">Recent withdrawals</div>
                                    @if($withdrawalRequests->isEmpty())
                                        <div class="text-secondary small">No withdrawal requests recorded yet.</div>
                                    @else
                                        <div class="d-grid gap-2">
                                            @foreach($withdrawalRequests as $withdrawal)
                                                <div class="small d-flex justify-content-between gap-2">
                                                    <span>{{ ucfirst($withdrawal->status ?? 'pending') }}</span>
                                                    <span>${{ number_format(((int) $withdrawal->amount_cents) / 100, 2) }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <div class="border rounded-3 p-3">
                                    <div class="fw-semibold mb-2">Recent wallet adjustments</div>
                                    @if($walletAdjustments->isEmpty())
                                        <div class="text-secondary small">No manual balance adjustments recorded yet.</div>
                                    @else
                                        <div class="d-grid gap-2">
                                            @foreach($walletAdjustments as $adjustment)
                                                <div class="small d-flex justify-content-between gap-2">
                                                    <span>{{ $adjustment->type === 'add' ? 'Add' : 'Deduct' }} · {{ $adjustment->admin?->fullIdentity('Admin') ?? 'Admin' }}</span>
                                                    <span>{{ $adjustment->type === 'add' ? '+' : '-' }}${{ number_format(((int) $adjustment->amount_cents) / 100, 2) }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="col-lg-6">
                    <section class="card app-card admin-section-card h-100">
                        <div class="card-body">
                            <h2 class="h5 mb-3">Audit Trail</h2>
                            @if($auditLogs->isEmpty())
                                <p class="text-secondary mb-0">No admin audit entries recorded against this booster yet.</p>
                            @else
                                <div class="d-grid gap-2">
                                    @foreach($auditLogs as $log)
                                        <div class="border rounded-3 p-3">
                                            <div class="d-flex justify-content-between gap-2">
                                                <div class="fw-semibold">{{ str_replace('_', ' ', \Illuminate\Support\Str::headline($log->action)) }}</div>
                                                <div class="small text-secondary">{{ $log->created_at?->format('M j, Y g:i A') ?? '-' }}</div>
                                            </div>
                                            <div class="small text-secondary">{{ $log->actor?->fullIdentity('Admin') ?? 'System' }} · {{ ucfirst($log->module ?? 'people') }}</div>
                                            @if(is_array($log->metadata) && count($log->metadata))
                                                <div class="small text-secondary mt-1">
                                                    @foreach(array_slice($log->metadata, 0, 3, true) as $key => $value)
                                                        <div>{{ \Illuminate\Support\Str::headline((string) $key) }}: {{ is_scalar($value) ? $value : json_encode($value) }}</div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
