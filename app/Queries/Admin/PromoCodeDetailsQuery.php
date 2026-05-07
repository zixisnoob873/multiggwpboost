<?php

namespace App\Queries\Admin;

use App\Models\Order;
use App\Models\PromoCode;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PromoCodeDetailsQuery
{
    public function execute(PromoCode $promoCode, Request $request): array
    {
        $promoCode->loadMissing('addonRules');
        $perPage = max(10, min(100, (int) $request->input('per_page', 20)));
        $orders = $promoCode->orders()
            ->with(['user:id,name,email'])
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();

        $usageCount = $promoCode->orders()->count();
        $totalDiscountByCurrency = $this->decimalTotalsByCurrency($promoCode, 'discount_amount');
        $totalOrderValueByCurrency = $this->centTotalsByCurrency($promoCode, 'price_cents');
        $totalOriginalOrderValueByCurrency = $this->rawTotalsByCurrency($promoCode, Order::originalPriceSql());
        $totalBoosterPayoutBasisByCurrency = $this->rawTotalsByCurrency($promoCode, Order::boosterPayoutBasisSql());

        return [
            'promoCode' => $promoCode,
            'usageCount' => $usageCount,
            'totalDiscountGivenLabel' => $this->formatTotals($totalDiscountByCurrency, false),
            'totalOrderValueLabel' => $this->formatTotals($totalOrderValueByCurrency, true),
            'totalOriginalOrderValueLabel' => $this->formatTotals($totalOriginalOrderValueByCurrency, true),
            'totalBoosterPayoutBasisLabel' => $this->formatTotals($totalBoosterPayoutBasisByCurrency, true),
            'orders' => $orders,
        ];
    }

    protected function centTotalsByCurrency(PromoCode $promoCode, string $column): Collection
    {
        return $promoCode->orders()
            ->selectRaw('currency, COALESCE(SUM('.$column.'), 0) as aggregate')
            ->groupBy('currency')
            ->get()
            ->reduce(function (Collection $totals, $row): Collection {
                $currency = $this->normalizeCurrency($row->currency);
                $totals[$currency] = (float) ($totals[$currency] ?? 0) + (float) $row->aggregate;

                return $totals;
            }, collect());
    }

    protected function decimalTotalsByCurrency(PromoCode $promoCode, string $column): Collection
    {
        return $promoCode->orders()
            ->selectRaw('currency, COALESCE(SUM('.$column.'), 0) as aggregate')
            ->groupBy('currency')
            ->get()
            ->reduce(function (Collection $totals, $row): Collection {
                $currency = $this->normalizeCurrency($row->currency);
                $totals[$currency] = (float) ($totals[$currency] ?? 0) + (float) $row->aggregate;

                return $totals;
            }, collect());
    }

    protected function rawTotalsByCurrency(PromoCode $promoCode, string $expression): Collection
    {
        return $promoCode->orders()
            ->selectRaw("currency, COALESCE(SUM({$expression}), 0) as aggregate")
            ->groupBy('currency')
            ->get()
            ->reduce(function (Collection $totals, $row): Collection {
                $currency = $this->normalizeCurrency($row->currency);
                $totals[$currency] = (float) ($totals[$currency] ?? 0) + (float) $row->aggregate;

                return $totals;
            }, collect());
    }

    protected function normalizeCurrency(?string $currency): string
    {
        $normalized = strtoupper(trim((string) $currency));

        return $normalized !== '' ? $normalized : 'USD';
    }

    protected function formatTotals(Collection $totals, bool $valueIsCents): string
    {
        if ($totals->isEmpty()) {
            return '$0.00';
        }

        return $totals
            ->sortKeys()
            ->map(function (float $amount, string $currency) use ($valueIsCents): string {
                $normalizedAmount = $valueIsCents ? ($amount / 100) : $amount;
                $formattedAmount = number_format($normalizedAmount, 2);

                return $currency === 'USD'
                    ? '$'.$formattedAmount
                    : sprintf('%s %s', $currency, $formattedAmount);
            })
            ->implode(', ');
    }
}
