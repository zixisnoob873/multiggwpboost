@extends('layouts.admin')

@section('title', 'GGWP Boost | Income Statement')

@php
    $totalSales = number_format(($totalSaleCents ?? 0) / 100, 2);
    $totalOriginalOrderValue = number_format(($totalOriginalOrderValueCents ?? 0) / 100, 2);
    $totalPromoDiscounts = number_format(($totalPromoDiscountCents ?? 0) / 100, 2);
    $totalBoosterPayouts = number_format(($totalBoosterPayoutsCents ?? 0) / 100, 2);
    $totalPlatformRevenue = number_format(($totalPlatformRevenueCents ?? 0) / 100, 2);
@endphp

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide">
    @include('admin.partials.page-header', [
        'title' => 'Income Statement',
        'subtitle' => 'Yearly revenue and payout reporting with export names that match the real output.',
        'actions' => [
            ['label' => 'Print View', 'href' => route('admin-income-statement.export.pdf', ['year' => $selectedYear]), 'class' => 'btn btn-danger btn-sm', 'target' => '_blank', 'rel' => 'noopener'],
            ['label' => 'Export Excel', 'href' => route('admin-income-statement.export.excel', ['year' => $selectedYear])],
            ['label' => 'Finance Overview', 'href' => route('admin-finance.index')],
        ],
    ])

    <form class="card app-card admin-filters-card mb-3" method="GET" action="{{ route('admin-income-statement') }}">
        <div class="card-body d-flex flex-wrap align-items-end gap-3">
            <div class="admin-filter-field">
                <label class="form-label">Report Year</label>
                <select class="form-select" name="year">
                    @foreach(($availableYears ?? collect([now()->year])) as $year)
                        <option value="{{ $year }}" {{ (int) ($selectedYear ?? now()->year) === (int) $year ? 'selected' : '' }}>{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-danger" type="submit">Generate</button>
        </div>
    </form>

    <div class="row g-3 mb-3">
        @foreach([
            ['label' => 'Customer Revenue', 'value' => '$'.$totalSales],
            ['label' => 'Original Order Value', 'value' => '$'.$totalOriginalOrderValue],
            ['label' => 'Promo Discounts', 'value' => '$'.$totalPromoDiscounts],
            ['label' => 'Booster Payouts', 'value' => '$'.$totalBoosterPayouts],
            ['label' => 'Platform Revenue', 'value' => '$'.$totalPlatformRevenue],
        ] as $card)
            <div class="col-md-6 col-xl">
                <div class="card app-card admin-stat-card h-100">
                    <div class="card-body">
                        <div class="admin-stat-card__label">{{ $card['label'] }}</div>
                        <div class="admin-stat-card__value">{{ $card['value'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <section class="card app-card admin-section-card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Customer Revenue</th>
                            <th>Original Value</th>
                            <th>Promo Discounts</th>
                            <th>Booster Payouts</th>
                            <th>Platform Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(($monthlyStatement ?? collect()) as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row['month_label'] }}</td>
                                <td>${{ number_format(($row['sale_cents'] ?? 0) / 100, 2) }}</td>
                                <td>${{ number_format(($row['original_order_value_cents'] ?? 0) / 100, 2) }}</td>
                                <td>${{ number_format(($row['promo_discount_cents'] ?? 0) / 100, 2) }}</td>
                                <td>${{ number_format(($row['booster_payouts_cents'] ?? 0) / 100, 2) }}</td>
                                <td>${{ number_format(($row['platform_revenue_cents'] ?? 0) / 100, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>Total</th>
                            <th>${{ $totalSales }}</th>
                            <th>${{ $totalOriginalOrderValue }}</th>
                            <th>${{ $totalPromoDiscounts }}</th>
                            <th>${{ $totalBoosterPayouts }}</th>
                            <th>${{ $totalPlatformRevenue }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </section>
</main>
@endsection
