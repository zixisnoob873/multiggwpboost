@php
    $money = fn ($cents) => '$'.number_format(((int) $cents) / 100, 2);
@endphp

@extends('layouts.layout')

@section('title', 'GGWP Boost | Printable Income Statement')

@push('head')
<style>
@media print {
  .admin-sidebar,
  .admin-topbar,
  .ggwp-page-actions,
  .footer,
  .site-footer {
    display: none !important;
  }

  .admin-main,
  .admin-content,
  .ggwp-page-shell {
    max-width: none !important;
    width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
  }
}
</style>
@endpush

@section('content')
<main class="ggwp-page-shell ggwp-page-shell--wide">
    <div class="ggwp-page-header mb-3">
        <div class="ggwp-page-header__copy">
            <h1 class="mb-0">Printable Income Statement</h1>
            <p class="text-secondary mb-0">Use the browser print dialog to save or print this report. This is the compatibility replacement for the old “PDF export” endpoint.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 ggwp-page-actions">
            <button class="btn btn-danger" type="button" onclick="window.print()">Print this report</button>
            <a class="btn btn-outline-light" href="{{ route('admin-income-statement', ['year' => $selectedYear]) }}">Back to Income Statement</a>
        </div>
    </div>

    <div class="admin-kpi-grid mb-3">
        <div class="admin-kpi-card"><span class="admin-kpi-card__label">Customer revenue</span><div class="admin-kpi-card__value">{{ $money($totalSaleCents ?? 0) }}</div></div>
        <div class="admin-kpi-card"><span class="admin-kpi-card__label">Promo discounts</span><div class="admin-kpi-card__value">{{ $money($totalPromoDiscountCents ?? 0) }}</div></div>
        <div class="admin-kpi-card"><span class="admin-kpi-card__label">Booster payouts</span><div class="admin-kpi-card__value">{{ $money($totalBoosterPayoutsCents ?? 0) }}</div></div>
        <div class="admin-kpi-card"><span class="admin-kpi-card__label">Platform revenue</span><div class="admin-kpi-card__value">{{ $money($totalPlatformRevenueCents ?? 0) }}</div></div>
    </div>

    <section class="card app-card ggwp-panel-card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 ggwp-data-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Customer revenue</th>
                            <th>Original order value</th>
                            <th>Promo discounts</th>
                            <th>Booster payouts</th>
                            <th>Platform revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(($monthlyStatement ?? collect()) as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row['month_label'] }}</td>
                                <td>{{ $money($row['sale_cents'] ?? 0) }}</td>
                                <td>{{ $money($row['original_order_value_cents'] ?? 0) }}</td>
                                <td>{{ $money($row['promo_discount_cents'] ?? 0) }}</td>
                                <td>{{ $money($row['booster_payouts_cents'] ?? 0) }}</td>
                                <td>{{ $money($row['platform_revenue_cents'] ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>Total</th>
                            <th>{{ $money($totalSaleCents ?? 0) }}</th>
                            <th>{{ $money($totalOriginalOrderValueCents ?? 0) }}</th>
                            <th>{{ $money($totalPromoDiscountCents ?? 0) }}</th>
                            <th>{{ $money($totalBoosterPayoutsCents ?? 0) }}</th>
                            <th>{{ $money($totalPlatformRevenueCents ?? 0) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </section>
</main>
@endsection
