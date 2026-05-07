@extends('layouts.admin')

@section('title', 'GGWP Boost | Customer Profile')

@section('admin_content')
@php
    $spendCents = (int) ($orderStats['spend_cents'] ?? 0);
@endphp
<main class="ggwp-page-shell ggwp-page-shell--wide">
    @include('admin.partials.page-header', [
        'title' => 'Customer Profile',
        'subtitle' => $customer->fullIdentity('Customer').' · '.$customer->email,
        'meta' => [
            'Nickname: '.$customer->publicIdentity('Customer'),
            'Status: '.ucfirst($customer->account_status ?? 'active'),
            'Joined: '.($customer->created_at?->format('M j, Y g:i A') ?? '-'),
        ],
        'actions' => [
            ['label' => 'Edit', 'href' => route('admin-customers.edit', $customer), 'class' => 'btn btn-danger btn-sm'],
            ['label' => 'Orders', 'href' => route('admin-total-order', ['search' => $customer->email])],
            ['label' => 'Back', 'href' => route('admin-customers.index')],
        ],
    ])

    <section class="admin-stat-grid">
        <article class="card app-card admin-stat-card">
            <div class="card-body">
                <div class="admin-stat-card__label">Total Orders</div>
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
                <div class="admin-stat-card__label">Completed Orders</div>
                <div class="admin-stat-card__value">{{ number_format($orderStats['completed'] ?? 0) }}</div>
            </div>
        </article>
        <article class="card app-card admin-stat-card">
            <div class="card-body">
                <div class="admin-stat-card__label">Tracked Spend</div>
                <div class="admin-stat-card__value">${{ number_format($spendCents / 100, 2) }}</div>
            </div>
        </article>
    </section>

    <div class="row g-3">
        <div class="col-xl-4">
            <section class="card app-card admin-section-card h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Account Details</h2>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-5 text-secondary">Full name</dt>
                        <dd class="col-sm-7">{{ $customer->fullIdentity('Customer') }}</dd>
                        <dt class="col-sm-5 text-secondary">Nickname</dt>
                        <dd class="col-sm-7">{{ $customer->publicIdentity('Customer') }}</dd>
                        <dt class="col-sm-5 text-secondary">Email</dt>
                        <dd class="col-sm-7">{{ $customer->email }}</dd>
                        <dt class="col-sm-5 text-secondary">Role</dt>
                        <dd class="col-sm-7 text-capitalize">{{ $customer->role }}</dd>
                        <dt class="col-sm-5 text-secondary">Status</dt>
                        <dd class="col-sm-7">
                            <span class="badge {{ $customer->account_status === 'suspended' ? 'text-bg-danger' : 'text-bg-success' }}">
                                {{ ucfirst($customer->account_status ?? 'active') }}
                            </span>
                        </dd>
                        <dt class="col-sm-5 text-secondary">Created</dt>
                        <dd class="col-sm-7">{{ $customer->created_at?->format('M j, Y g:i A') ?? '-' }}</dd>
                        <dt class="col-sm-5 text-secondary">Updated</dt>
                        <dd class="col-sm-7">{{ $customer->updated_at?->format('M j, Y g:i A') ?? '-' }}</dd>
                        <dt class="col-sm-5 text-secondary">Email verified</dt>
                        <dd class="col-sm-7">{{ $customer->email_verified_at?->format('M j, Y g:i A') ?? 'Not verified' }}</dd>
                    </dl>

                    <div class="d-grid gap-2 mt-3">
                        <a class="btn btn-outline-light" href="{{ route('admin-customers.edit', $customer) }}">Edit account</a>
                        <form action="{{ route('admin-customers.status', $customer) }}" method="POST" data-loading-form>
                            @csrf
                            @method('PATCH')
                            <button class="btn {{ $customer->account_status === 'suspended' ? 'btn-success' : 'btn-warning' }} w-100" type="submit" data-busy-label="Saving...">
                                {{ $customer->account_status === 'suspended' ? 'Activate Customer' : 'Suspend Customer' }}
                            </button>
                        </form>
                    </div>
                </div>
            </section>
        </div>

        <div class="col-xl-8">
            <section class="card app-card admin-section-card mb-3">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                        <div>
                            <h2 class="h5 mb-1">Recent Orders</h2>
                            <p class="text-secondary mb-0">Latest operational records linked to this customer.</p>
                        </div>
                        <a class="btn btn-outline-light btn-sm" href="{{ route('admin-total-order', ['search' => $customer->email]) }}">Open filtered orders</a>
                    </div>

                    @if($recentOrders->isEmpty())
                        @include('admin.partials.empty-state', [
                            'title' => 'No orders yet',
                            'copy' => 'This customer does not have any linked boost orders yet.',
                        ])
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Booster</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th>Total</th>
                                        <th>Created</th>
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
                                            <td>{{ $order->booster?->publicIdentity('Unassigned') ?? 'Unassigned' }}</td>
                                            <td><span class="badge {{ $order->statusBadgeClass() }}">{{ $order->statusLabel() }}</span></td>
                                            <td>{{ ucfirst($order->payment_status ?? 'pending') }}</td>
                                            <td>${{ number_format($order->customerPriceCents() / 100, 2) }}</td>
                                            <td>{{ $order->created_at?->format('M j, Y') ?? '-' }}</td>
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

            <div class="row g-3">
                <div class="col-lg-6">
                    <section class="card app-card admin-section-card h-100">
                        <div class="card-body">
                            <h2 class="h5 mb-3">Support Context</h2>
                            @if($contactMessages->isEmpty())
                                <p class="text-secondary mb-0">No linked contact inbox records for this customer yet.</p>
                            @else
                                <div class="d-grid gap-2">
                                    @foreach($contactMessages as $message)
                                        <div class="border rounded-3 p-3">
                                            <div class="d-flex justify-content-between gap-2">
                                                <div class="fw-semibold">{{ $message->name }}</div>
                                                <span class="badge text-bg-secondary">{{ $message->statusLabel() }}</span>
                                            </div>
                                            <div class="small text-secondary">{{ $message->email }} · {{ $message->created_at?->format('M j, Y g:i A') ?? '-' }}</div>
                                            <p class="small mb-2 mt-2">{{ \Illuminate\Support\Str::limit($message->message, 120) }}</p>
                                            <a class="btn btn-outline-light btn-sm" href="{{ route('admin-contact-messages.edit', $message) }}">Open message</a>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </section>
                </div>

                <div class="col-lg-6">
                    <section class="card app-card admin-section-card h-100">
                        <div class="card-body">
                            <h2 class="h5 mb-3">Audit Trail</h2>
                            @if($auditLogs->isEmpty())
                                <p class="text-secondary mb-0">No admin audit entries recorded against this customer yet.</p>
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
