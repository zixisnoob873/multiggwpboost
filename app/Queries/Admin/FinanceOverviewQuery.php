<?php

namespace App\Queries\Admin;

use App\Models\BoosterWalletAdjustment;
use App\Models\Order;
use App\Models\WithdrawalRequest;
use App\Services\IncomeStatementService;

class FinanceOverviewQuery
{
    public function __construct(
        protected IncomeStatementService $incomeStatementService,
    ) {}

    public function execute(int $year): array
    {
        $statement = $this->incomeStatementService->payloadForYear($year);
        $selectedYear = (int) ($statement['selectedYear'] ?? $year);
        $monthRows = collect($statement['monthlyStatement'] ?? []);
        $pendingWithdrawals = WithdrawalRequest::query()->where('status', WithdrawalRequest::STATUS_PENDING);
        $walletAdjustments = BoosterWalletAdjustment::query()->latest('created_at')->with(['booster', 'admin'])->limit(10)->get();

        return array_merge($statement, [
            'selectedYear' => $selectedYear,
            'pendingWithdrawalsCount' => (clone $pendingWithdrawals)->count(),
            'pendingWithdrawalAmountCents' => (int) ((clone $pendingWithdrawals)->sum('amount_cents') ?? 0),
            'walletAdjustmentCount' => BoosterWalletAdjustment::query()->count(),
            'recentWalletAdjustments' => $walletAdjustments,
            'monthlyChartPoints' => $monthRows->map(fn (array $row): array => [
                'label' => $row['month_label'],
                'sale_cents' => $row['sale_cents'],
                'platform_revenue_cents' => $row['platform_revenue_cents'],
                'promo_discount_cents' => $row['promo_discount_cents'],
            ])->values(),
            'overviewStats' => [
                'gross_sales_cents' => (int) ($statement['totalSaleCents'] ?? 0),
                'original_sales_cents' => (int) ($statement['totalOriginalOrderValueCents'] ?? 0),
                'discounts_cents' => (int) ($statement['totalPromoDiscountCents'] ?? 0),
                'payouts_cents' => (int) ($statement['totalBoosterPayoutsCents'] ?? 0),
                'platform_revenue_cents' => (int) ($statement['totalPlatformRevenueCents'] ?? 0),
                'orders_count' => Order::query()->whereYear('created_at', $selectedYear)->count(),
            ],
        ]);
    }
}
