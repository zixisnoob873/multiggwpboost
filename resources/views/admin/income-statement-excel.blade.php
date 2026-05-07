<table border="1">
    @php
        $payoutRates = collect($payoutRateSummary['rates'] ?? []);
        $payoutRateText = (($payoutRateSummary['is_mixed'] ?? false) && $payoutRates->isNotEmpty())
            ? 'Stored payout values from the pre-promo payout basis. Mixed rates: '.$payoutRates->map(fn ($rate) => rtrim(rtrim(number_format((float) $rate, 2, '.', ''), '0'), '.').'%')->implode(', ')
            : 'Stored payout values from the pre-promo payout basis. Rate: '.rtrim(rtrim(number_format((float) (($payoutRates->first() ?? ($payoutRateSummary['default_rate'] ?? 60))), 2, '.', ''), '0'), '.').'%';
    @endphp
    <thead>
        <tr>
            <th colspan="6">Income Statement - {{ $selectedYear }}</th>
        </tr>
        <tr>
            <th colspan="6">{{ $payoutRateText }}</th>
        </tr>
        <tr>
            <th>Month</th>
            <th>Customer Revenue</th>
            <th>Original Order Value</th>
            <th>Promo Discounts</th>
            <th>Booster Payouts</th>
            <th>Platform Revenue</th>
        </tr>
    </thead>
    <tbody>
        @foreach(($monthlyStatement ?? collect()) as $row)
            <tr>
                <td>{{ $row['month_label'] }}</td>
                <td>{{ number_format(($row['sale_cents'] ?? 0) / 100, 2, '.', '') }}</td>
                <td>{{ number_format(($row['original_order_value_cents'] ?? 0) / 100, 2, '.', '') }}</td>
                <td>{{ number_format(($row['promo_discount_cents'] ?? 0) / 100, 2, '.', '') }}</td>
                <td>{{ number_format(($row['booster_payouts_cents'] ?? 0) / 100, 2, '.', '') }}</td>
                <td>{{ number_format(($row['platform_revenue_cents'] ?? 0) / 100, 2, '.', '') }}</td>
            </tr>
        @endforeach
        <tr>
            <th>Total</th>
            <th>{{ number_format(($totalSaleCents ?? 0) / 100, 2, '.', '') }}</th>
            <th>{{ number_format(($totalOriginalOrderValueCents ?? 0) / 100, 2, '.', '') }}</th>
            <th>{{ number_format(($totalPromoDiscountCents ?? 0) / 100, 2, '.', '') }}</th>
            <th>{{ number_format(($totalBoosterPayoutsCents ?? 0) / 100, 2, '.', '') }}</th>
            <th>{{ number_format(($totalPlatformRevenueCents ?? 0) / 100, 2, '.', '') }}</th>
        </tr>
    </tbody>
</table>
