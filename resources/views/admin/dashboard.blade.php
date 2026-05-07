@extends('layouts.admin')

@section('title', 'GGWP Boost | Dashboard')

@php
    use App\Support\AdminPermission;

    $adminUser = auth()->user();
    $period = $selectedPeriod ?? 'current_month';
    $maintenanceEnabled = (bool) ($maintenanceModeEnabled ?? false);
    $dashboardNotice = trim((string) ($systemSettings['dashboard_notice'] ?? ''));
    $deploymentNotice = trim((string) ($systemSettings['deployment_notice'] ?? ''));
    $canAccessSystem = $adminUser?->canAccessAdminModule('system') ?? false;
    $canViewPricing = $canAccessSystem && AdminPermission::userCan($adminUser, 'system.pricing.view');
    $financeCards = [
        ['label' => 'Revenue', 'value' => '$'.number_format(($totalSaleCents ?? 0) / 100, 2)],
        ['label' => 'Payouts', 'value' => '$'.number_format(($estimatedBoosterPayoutsCents ?? 0) / 100, 2)],
        ['label' => 'Promo Discounts', 'value' => '$'.number_format(($totalDiscountCents ?? 0) / 100, 2)],
        ['label' => 'Platform Revenue', 'value' => '$'.number_format(($estimatedNetRevenueCents ?? 0) / 100, 2)],
        ['label' => 'Tips', 'value' => '$'.number_format(($adminTipsCents ?? 0) / 100, 2)],
    ];
@endphp

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide admin-page admin-page--dense">
    @include('admin.partials.page-header', [
        'title' => 'Dashboard',
        'subtitle' => 'Operations snapshot.',
        'meta' => [
            'Period: '.($period === 'all_time' ? 'All Time' : 'Current Month'),
            'Maintenance: '.($maintenanceEnabled ? 'On' : 'Off'),
        ],
        'actions' => array_values(array_filter([
            auth()->user()?->canAccessAdminModule('operations') ? ['label' => 'Orders', 'href' => route('admin-total-order'), 'class' => 'btn btn-danger btn-sm'] : null,
            auth()->user()?->canAccessAdminModule('operations') ? ['label' => 'Chats', 'href' => route('admin-chats')] : null,
            auth()->user()?->canAccessAdminModule('finance') ? ['label' => 'Finance', 'href' => route('admin-finance.index')] : null,
            $canAccessSystem ? ['label' => 'System', 'href' => route('admin-system.settings')] : null,
        ])),
    ])

    <form class="card app-card admin-filters-card" method="GET" action="{{ route('admin-dashboard') }}">
        <div class="card-body d-flex flex-wrap align-items-end gap-3">
            <div class="admin-filter-field flex-grow-1">
                <label class="form-label mb-1">Business summary period</label>
                <select class="form-select" name="period">
                    <option value="current_month" {{ $period === 'current_month' ? 'selected' : '' }}>Current Month</option>
                    <option value="all_time" {{ $period === 'all_time' ? 'selected' : '' }}>All Time</option>
                </select>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-danger" type="submit">Apply</button>
                @if($canAccessSystem)
                    <a class="btn btn-outline-light" href="{{ route('admin-system.maintenance.index') }}">Maintenance Mode</a>
                @endif
            </div>
        </div>
    </form>

    <section class="admin-stat-grid">
        <article class="card app-card admin-stat-card">
            <div class="card-body">
                <div class="admin-stat-card__label">Active Orders</div>
                <div class="admin-stat-card__value">{{ number_format($operationalHealth['active_orders'] ?? 0) }}</div>
            </div>
        </article>
        <article class="card app-card admin-stat-card">
            <div class="card-body">
                <div class="admin-stat-card__label">Needs Assignment</div>
                <div class="admin-stat-card__value">{{ number_format($operationalHealth['needs_assignment'] ?? 0) }}</div>
            </div>
        </article>
        <article class="card app-card admin-stat-card">
            <div class="card-body">
                <div class="admin-stat-card__label">Paused Orders</div>
                <div class="admin-stat-card__value">{{ number_format($operationalHealth['paused_orders'] ?? 0) }}</div>
            </div>
        </article>
        <article class="card app-card admin-stat-card">
            <div class="card-body">
                <div class="admin-stat-card__label">Pending Withdrawals</div>
                <div class="admin-stat-card__value">{{ number_format($operationalHealth['pending_withdrawals'] ?? 0) }}</div>
            </div>
        </article>
        <article class="card app-card admin-stat-card">
            <div class="card-body">
                <div class="admin-stat-card__label">Unread Inbox</div>
                <div class="admin-stat-card__value">{{ number_format($operationalHealth['unread_contact_messages'] ?? 0) }}</div>
            </div>
        </article>
        <article class="card app-card admin-stat-card">
            <div class="card-body">
                <div class="admin-stat-card__label">New Applications</div>
                <div class="admin-stat-card__value">{{ number_format($operationalHealth['new_booster_applications'] ?? 0) }}</div>
            </div>
        </article>
    </section>

    <div class="row g-2">
        <div class="col-xl-8">
            <section class="card app-card admin-section-card h-100">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h2 class="h5 mb-0">Action Queues</h2>
                        @if(auth()->user()?->canAccessAdminModule('operations'))
                            <a class="btn btn-outline-light btn-sm" href="{{ route('admin-total-order', ['tab' => 'needs_assignment']) }}">Open Operations</a>
                        @endif
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="card app-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h3 class="h6 mb-0">Orders Needing Action</h3>
                                        <a class="small text-decoration-none" href="{{ route('admin-total-order', ['tab' => 'needs_assignment']) }}">View all</a>
                                    </div>
                                    @forelse($ordersNeedingAction as $order)
                                        <div class="d-flex justify-content-between gap-2 py-2 border-bottom border-secondary-subtle">
                                            <div>
                                                <div class="fw-semibold">#{{ $order->order_number }}</div>
                                                <div class="small text-secondary">{{ $order->user?->fullIdentity('Customer') }} · {{ $order->serviceName() }}</div>
                                            </div>
                                            <div class="text-end">
                                                <div>@include('partials.order-status-badge', ['status' => $order->status])</div>
                                                <a class="small text-decoration-none" href="{{ route('admin-orders.edit', $order) }}">Open</a>
                                            </div>
                                        </div>
                                    @empty
                                        @include('admin.partials.empty-state', [
                                            'title' => 'No urgent order queue',
                                            'copy' => 'Assignment gaps and paused orders appear here.',
                                        ])
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card app-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h3 class="h6 mb-0">Chats Needing Reply</h3>
                                        <a class="small text-decoration-none" href="{{ route('admin-chats', ['reply_state' => 'needs_reply']) }}">View all</a>
                                    </div>
                                    @forelse($openChatsNeedingReply as $order)
                                        <div class="d-flex justify-content-between gap-2 py-2 border-bottom border-secondary-subtle">
                                            <div>
                                                <div class="fw-semibold">#{{ $order->order_number }}</div>
                                                <div class="small text-secondary">{{ $order->user?->fullIdentity('Customer') }} · {{ $order->latest_chat_at ? \Illuminate\Support\Carbon::parse($order->latest_chat_at)->diffForHumans() : 'Recent activity' }}</div>
                                            </div>
                                            <a class="btn btn-outline-light btn-sm align-self-start" href="{{ route('admin-chats.show', $order) }}">Reply</a>
                                        </div>
                                    @empty
                                        @include('admin.partials.empty-state', [
                                            'title' => 'No reply queue',
                                            'copy' => 'Customer and booster replies appear here.',
                                        ])
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card app-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h3 class="h6 mb-0">Pending Withdrawals</h3>
                                        <a class="small text-decoration-none" href="{{ route('admin-withdrawal-requests.index', ['status' => 'pending']) }}">View all</a>
                                    </div>
                                    @forelse($pendingWithdrawals as $request)
                                        <div class="d-flex justify-content-between gap-2 py-2 border-bottom border-secondary-subtle">
                                            <div>
                                                <div class="fw-semibold">{{ $request->booster?->fullIdentity('Booster') }}</div>
                                                <div class="small text-secondary">{{ $request->created_at?->diffForHumans() ?? '-' }}</div>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-semibold">${{ number_format(($request->amount_cents ?? 0) / 100, 2) }}</div>
                                                <a class="small text-decoration-none" href="{{ route('admin-withdrawal-requests.index', ['status' => 'pending']) }}">Review</a>
                                            </div>
                                        </div>
                                    @empty
                                        @include('admin.partials.empty-state', [
                                            'title' => 'No pending withdrawals',
                                            'copy' => 'New payout requests appear here.',
                                        ])
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card app-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h3 class="h6 mb-0">Applications And Inbox</h3>
                                        <a class="small text-decoration-none" href="{{ route('admin-booster-applications') }}">View all</a>
                                    </div>
                                    @forelse($newBoosterApplications as $application)
                                        <div class="d-flex justify-content-between gap-2 py-2 border-bottom border-secondary-subtle">
                                            <div>
                                                <div class="fw-semibold">{{ $application->name }}</div>
                                                <div class="small text-secondary">{{ $application->email }}</div>
                                            </div>
                                            <a class="btn btn-outline-light btn-sm align-self-start" href="{{ route('admin-booster-applications.edit', $application) }}">Review</a>
                                        </div>
                                    @empty
                                        <div class="small text-secondary mb-2">No new booster applications.</div>
                                    @endforelse

                                    @foreach($newInboxMessages as $message)
                                        <div class="d-flex justify-content-between gap-2 py-2 border-bottom border-secondary-subtle">
                                            <div>
                                                <div class="fw-semibold">{{ $message->name }}</div>
                                                <div class="small text-secondary">{{ $message->email }} · {{ ucfirst($message->status) }}</div>
                                            </div>
                                            <a class="btn btn-outline-light btn-sm align-self-start" href="{{ route('admin-contact-messages.edit', $message) }}">Open</a>
                                        </div>
                                    @endforeach

                                    @if(($newBoosterApplications->count() + $newInboxMessages->count()) === 0)
                                        @include('admin.partials.empty-state', [
                                            'title' => 'No people queue',
                                            'copy' => 'Applications and inbox items appear here.',
                                        ])
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="col-xl-4">
            <section class="card app-card admin-section-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">System Panel</h2>
                        @if($canAccessSystem || $canViewPricing)
                            <div class="d-flex flex-wrap gap-2">
                                @if($canAccessSystem)
                                    <a class="btn btn-outline-light btn-sm" href="{{ route('admin-system.settings') }}">Open System</a>
                                @endif
                                @if($canViewPricing)
                                    <a class="btn btn-danger btn-sm" href="{{ route('admin-pricing.index') }}">Pricing</a>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-secondary-subtle">
                        <span>Maintenance Mode</span>
                        <span class="badge {{ $maintenanceEnabled ? 'text-bg-warning text-dark' : 'text-bg-success' }}">{{ $maintenanceEnabled ? 'On' : 'Off' }}</span>
                    </div>
                    @if(($systemHealth['jobs_pending'] ?? null) !== null)
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-secondary-subtle">
                            <span>Queued Jobs</span>
                            <span>{{ number_format($systemHealth['jobs_pending']) }}</span>
                        </div>
                    @endif
                    @if(($systemHealth['failed_jobs'] ?? null) !== null)
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-secondary-subtle">
                            <span>Failed Jobs</span>
                            <span class="{{ ($systemHealth['failed_jobs'] ?? 0) > 0 ? 'text-danger' : '' }}">{{ number_format($systemHealth['failed_jobs']) }}</span>
                        </div>
                    @endif
                    @if(($systemHealth['failed_customer_emails'] ?? null) !== null)
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-secondary-subtle">
                            <span>Failed Customer Emails</span>
                            <span class="{{ ($systemHealth['failed_customer_emails'] ?? 0) > 0 ? 'text-danger' : '' }}">{{ number_format($systemHealth['failed_customer_emails']) }}</span>
                        </div>
                    @endif
                    @if(($systemHealth['failed_discord_dispatches'] ?? null) !== null)
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-secondary-subtle">
                            <span>Failed Discord Dispatches</span>
                            <span class="{{ ($systemHealth['failed_discord_dispatches'] ?? 0) > 0 ? 'text-danger' : '' }}">{{ number_format($systemHealth['failed_discord_dispatches']) }}</span>
                        </div>
                    @endif

                    @if($dashboardNotice !== '')
                        <div class="alert alert-info mt-3 mb-0">{{ $dashboardNotice }}</div>
                    @endif

                    @if($deploymentNotice !== '')
                        <div class="alert alert-warning mt-3 mb-0">{{ $deploymentNotice }}</div>
                    @endif
                </div>
            </section>
        </div>
    </div>

    <section class="card app-card admin-section-card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="h5 mb-0">Business Summary</h2>
                @if(auth()->user()?->canAccessAdminModule('finance'))
                    <a class="btn btn-outline-light btn-sm" href="{{ route('admin-income-statement', ['year' => now()->year]) }}">Open Income Statement</a>
                @endif
            </div>

            <div class="row g-2 mb-3">
                @foreach($financeCards as $card)
                    <div class="col-md-6 col-xl-3">
                        <div class="card app-card h-100">
                            <div class="card-body">
                                <div class="admin-stat-card__label">{{ $card['label'] }}</div>
                                <div class="admin-stat-card__value">{{ $card['value'] }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <p class="small text-secondary mb-3">Admin tips tracked separately from order revenue.</p>

            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Recent Customers</th>
                            <th>Email</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customers as $customer)
                            <tr>
                                <td class="fw-semibold">{{ $customer->name }}</td>
                                <td>{{ $customer->email }}</td>
                                <td>{{ $customer->created_at?->format('M j, Y g:i A') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3">
                                    @include('admin.partials.empty-state', [
                                        'title' => 'No recent customers',
                                        'copy' => 'No customer signups match the selected period.',
                                    ])
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>
@endsection
