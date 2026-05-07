@extends('layouts.admin')

@section('title', 'GGWP Boost | Promo Code Details')


@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide admin-page admin-page--dense">
    <div class="admin-page-header">
        <div class="admin-page-header__copy">
            <h1 class="admin-page-title">{{ $promoCode->code }}</h1>
            <div class="admin-page-meta">
                <span class="admin-page-meta__item">{{ $promoCode->typeLabel() }}</span>
                <span class="admin-page-meta__item">{{ $promoCode->displayValue() }}</span>
                <span class="admin-page-meta__item">{{ $promoCode->is_active ? 'Active' : 'Inactive' }}</span>
            </div>
        </div>

        <div class="admin-page-actions">
            <a class="btn btn-outline-light btn-sm" href="{{ route('admin-promo-codes.edit', $promoCode) }}">Edit Promo Code</a>
            <a class="btn btn-outline-light btn-sm" href="{{ route('admin-promo-codes.index') }}">Back to Promo Codes</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6 col-lg-3">
            <section class="card app-card admin-section-card h-100">
                <div class="card-body">
                    <div class="text-secondary small mb-1">Times Used</div>
                    <div class="h4 mb-1">{{ number_format($usageCount) }}</div>
                    <div class="small text-secondary">Limit {{ $promoCode->max_uses ?? 'Unlimited' }}</div>
                </div>
            </section>
        </div>
        <div class="col-md-6 col-lg-3">
            <section class="card app-card admin-section-card h-100">
                <div class="card-body">
                    <div class="text-secondary small mb-1">Discount Given</div>
                    <div class="h4 mb-0">{{ $totalDiscountGivenLabel }}</div>
                </div>
            </section>
        </div>
        <div class="col-md-6 col-lg-3">
            <section class="card app-card admin-section-card h-100">
                <div class="card-body">
                    <div class="text-secondary small mb-1">Customer Revenue</div>
                    <div class="h4 mb-0">{{ $totalOrderValueLabel }}</div>
                </div>
            </section>
        </div>
        <div class="col-md-6 col-lg-3">
            <section class="card app-card admin-section-card h-100">
                <div class="card-body">
                    <div class="text-secondary small mb-1">Original Order Value</div>
                    <div class="h4 mb-0">{{ $totalOriginalOrderValueLabel }}</div>
                </div>
            </section>
        </div>
        <div class="col-md-6 col-lg-3">
            <section class="card app-card admin-section-card h-100">
                <div class="card-body">
                    <div class="text-secondary small mb-1">Booster Payout Basis</div>
                    <div class="h4 mb-0">{{ $totalBoosterPayoutBasisLabel }}</div>
                </div>
            </section>
        </div>
    </div>

    @if($promoCode->usesAddonRules())
        <section class="card app-card admin-section-card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h2 class="h6 mb-0">Addon Rules</h2>
                    <span class="admin-chip">{{ $promoCode->addonRules->count() }} rules</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0 ggwp-data-table">
                        <thead>
                            <tr>
                                <th>Addon</th>
                                <th>Rule</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($promoCode->addonRules as $addonRule)
                                <tr>
                                    <td class="fw-semibold">{{ $addonRule->addonLabel() }}</td>
                                    <td>{{ $addonRule->discountTypeLabel() }}</td>
                                    <td>{{ $addonRule->displayDiscountValue() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @endif

    <section class="card app-card admin-section-card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="h6 mb-0">Orders Using This Code</h2>
                <span class="admin-chip">{{ $orders->total() }} orders</span>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 ggwp-data-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Customer Total</th>
                            <th>Original Price</th>
                            <th>Discount</th>
                            <th>Booster Payout Basis</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            @php
                                $customerName = $order->user?->name
                                    ?: trim(implode(' ', [
                                        data_get($order->metadata, 'customer.firstName', ''),
                                        data_get($order->metadata, 'customer.lastName', ''),
                                    ]))
                                    ?: $order->user?->email
                                    ?: data_get($order->metadata, 'customer.email', 'Customer');
                                $customerEmail = $order->user?->email ?: data_get($order->metadata, 'customer.email');
                                $currency = strtoupper((string) ($order->currency ?? 'USD'));
                                $customerTotal = number_format($order->customerPriceCents() / 100, 2);
                                $originalTotal = number_format($order->resolvedOriginalPriceCents() / 100, 2);
                                $discountAmount = number_format($order->resolvedDiscountAmountCents() / 100, 2);
                                $payoutBasis = number_format($order->resolvedBoosterPayoutBasisCents() / 100, 2);
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $order->order_number ?: $order->id }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $customerName }}</div>
                                    <div class="text-secondary small">{{ $customerEmail ?: '-' }}</div>
                                </td>
                                <td>
                                    <div>{{ $order->serviceName() }}</div>
                                    <div class="text-secondary small">{{ $order->product }}</div>
                                </td>
                                <td>{{ $currency === 'USD' ? '$'.$customerTotal : $currency.' '.$customerTotal }}</td>
                                <td>{{ $currency === 'USD' ? '$'.$originalTotal : $currency.' '.$originalTotal }}</td>
                                <td>{{ $currency === 'USD' ? '$'.$discountAmount : $currency.' '.$discountAmount }}</td>
                                <td>{{ $currency === 'USD' ? '$'.$payoutBasis : $currency.' '.$payoutBasis }}</td>
                                <td>
                                    <span class="badge {{ $order->statusBadgeClass() }}">{{ $order->statusLabel() }}</span>
                                </td>
                                <td>{{ $order->created_at?->format('M j, Y g:i A') ?? '-' }}</td>
                                <td class="text-end">
                                    <a class="btn btn-outline-light btn-sm" href="{{ route('admin-orders.edit', $order) }}">Open Order</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-secondary py-4">No orders have used this promo code yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $orders->withQueryString()->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </section>
</main>
@endsection
