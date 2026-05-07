<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \App\Support\PageTitle::format("Income Statement {$selectedYear}") }}</title>
    @include('partials.favicons')
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #111827;
            margin: 24px;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        p {
            margin: 0 0 16px;
            color: #4b5563;
        }

        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }

        .summary td {
            border: 1px solid #d1d5db;
            padding: 10px 12px;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
        }

        .report th,
        .report td {
            border: 1px solid #d1d5db;
            padding: 10px 12px;
            text-align: left;
        }

        .report tfoot th {
            background: #f3f4f6;
        }

        @media print {
            body {
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <h1>Income Statement {{ $selectedYear }}</h1>
    <p>Monthly customer revenue, original order value, promo discounts, booster payouts, and platform revenue.</p>
    @php
        $payoutRates = collect($payoutRateSummary['rates'] ?? []);
        $payoutRateText = (($payoutRateSummary['is_mixed'] ?? false) && $payoutRates->isNotEmpty())
            ? 'Stored payout values from the pre-promo payout basis. Mixed rates: '.$payoutRates->map(fn ($rate) => rtrim(rtrim(number_format((float) $rate, 2, '.', ''), '0'), '.').'%')->implode(', ')
            : 'Stored payout values from the pre-promo payout basis. Rate: '.rtrim(rtrim(number_format((float) (($payoutRates->first() ?? ($payoutRateSummary['default_rate'] ?? 60))), 2, '.', ''), '0'), '.').'%';
    @endphp
    <p>{{ $payoutRateText }}</p>

    <table class="summary">
        <tr>
            <td><strong>Customer revenue</strong><br>${{ number_format(($totalSaleCents ?? 0) / 100, 2) }}</td>
            <td><strong>Original order value</strong><br>${{ number_format(($totalOriginalOrderValueCents ?? 0) / 100, 2) }}</td>
            <td><strong>Promo discounts</strong><br>${{ number_format(($totalPromoDiscountCents ?? 0) / 100, 2) }}</td>
            <td><strong>Booster payouts</strong><br>${{ number_format(($totalBoosterPayoutsCents ?? 0) / 100, 2) }}</td>
            <td><strong>Platform revenue</strong><br>${{ number_format(($totalPlatformRevenueCents ?? 0) / 100, 2) }}</td>
        </tr>
    </table>

    <table class="report">
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
                    <td>{{ $row['month_label'] }}</td>
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
                <th>${{ number_format(($totalSaleCents ?? 0) / 100, 2) }}</th>
                <th>${{ number_format(($totalOriginalOrderValueCents ?? 0) / 100, 2) }}</th>
                <th>${{ number_format(($totalPromoDiscountCents ?? 0) / 100, 2) }}</th>
                <th>${{ number_format(($totalBoosterPayoutsCents ?? 0) / 100, 2) }}</th>
                <th>${{ number_format(($totalPlatformRevenueCents ?? 0) / 100, 2) }}</th>
            </tr>
        </tfoot>
    </table>

    <script nonce="{{ $cspNonce ?? '' }}">
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
</body>
</html>
