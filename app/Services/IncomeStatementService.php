<?php

namespace App\Services;

use App\Models\Order;

class IncomeStatementService
{
    public function payloadForYear(?int $requestedYear = null): array
    {
        $availableYears = Order::query()
            ->selectRaw('YEAR(created_at) as report_year')
            ->whereNotNull('created_at')
            ->distinct()
            ->orderByDesc('report_year')
            ->pluck('report_year')
            ->filter()
            ->map(fn ($year) => (int) $year)
            ->values();

        $selectedYear = $requestedYear ?: now()->year;
        if (! $availableYears->contains($selectedYear)) {
            $selectedYear = $availableYears->first() ?: now()->year;
        }

        $orders = Order::query()
            ->whereYear('created_at', $selectedYear)
            ->get(['price_cents', 'original_price_cents', 'discount_amount', 'booster_payout_basis_cents', 'booster_payout_cents', 'booster_payout_rate', 'created_at']);

        $payoutRates = $orders
            ->map(function (Order $order) {
                if ($order->booster_payout_rate !== null) {
                    return $this->normalizePayoutRatePercentage((float) $order->booster_payout_rate);
                }

                $payoutBasisCents = $order->resolvedBoosterPayoutBasisCents();
                if ($payoutBasisCents > 0 && $order->booster_payout_cents !== null) {
                    return round((((int) $order->booster_payout_cents) / $payoutBasisCents) * 100, 2);
                }

                return round(Order::configuredBoosterPayoutPercentage(), 2);
            })
            ->unique()
            ->sort()
            ->values();

        $payoutRateSummary = [
            'is_mixed' => $payoutRates->count() > 1,
            'rates' => $payoutRates,
            'default_rate' => round(Order::configuredBoosterPayoutPercentage(), 2),
        ];

        $monthlyStatement = collect(range(1, 12))
            ->map(function (int $month) use ($orders) {
                $monthOrders = $orders->filter(fn ($order) => (int) $order->created_at?->month === $month);
                $saleCents = (int) $monthOrders->sum('price_cents');
                $originalOrderValueCents = (int) $monthOrders->sum(fn (Order $order) => $order->resolvedOriginalPriceCents());
                $promoDiscountCents = (int) $monthOrders->sum(fn (Order $order) => $order->resolvedDiscountAmountCents());
                $boosterPayoutsCents = (int) $monthOrders->sum(fn (Order $order) => $order->resolvedBoosterPayoutCents());
                $platformRevenueCents = $saleCents - $boosterPayoutsCents;

                return [
                    'month_number' => $month,
                    'month_label' => now()->month($month)->format('F'),
                    'sale_cents' => $saleCents,
                    'original_order_value_cents' => $originalOrderValueCents,
                    'promo_discount_cents' => $promoDiscountCents,
                    'booster_payouts_cents' => $boosterPayoutsCents,
                    'platform_revenue_cents' => $platformRevenueCents,
                ];
            });

        return [
            'availableYears' => $availableYears,
            'selectedYear' => $selectedYear,
            'monthlyStatement' => $monthlyStatement,
            'totalSaleCents' => $monthlyStatement->sum('sale_cents'),
            'totalOriginalOrderValueCents' => $monthlyStatement->sum('original_order_value_cents'),
            'totalPromoDiscountCents' => $monthlyStatement->sum('promo_discount_cents'),
            'totalBoosterPayoutsCents' => $monthlyStatement->sum('booster_payouts_cents'),
            'totalPlatformRevenueCents' => $monthlyStatement->sum('platform_revenue_cents'),
            'payoutRateSummary' => $payoutRateSummary,
        ];
    }

    public function normalizePayoutRatePercentage(float $rate): float
    {
        if ($rate <= 1) {
            return round($rate * 100, 2);
        }

        return round($rate, 2);
    }
}
