<?php

namespace App\Services\Orders;

use App\Models\Order;

class OrderFinancialsService
{
    public function fromOriginalPriceCents(int $originalPriceCents, float $discountAmount = 0, ?float $payoutRate = null): array
    {
        $originalPriceCents = max(0, $originalPriceCents);
        $discountAmountCents = $this->normalizeDiscountAmountCents($discountAmount, $originalPriceCents);
        $customerPriceCents = max(0, $originalPriceCents - $discountAmountCents);
        $normalizedPayoutPercentage = $this->normalizePayoutPercentage($payoutRate);

        return [
            'price_cents' => $customerPriceCents,
            'original_price_cents' => $originalPriceCents,
            'discount_amount' => $this->toMoney($discountAmountCents),
            'discount_amount_cents' => $discountAmountCents,
            'booster_payout_basis_cents' => $originalPriceCents,
            'booster_payout_rate' => $normalizedPayoutPercentage,
            'booster_payout_cents' => (int) round($originalPriceCents * $this->percentageToMultiplier($normalizedPayoutPercentage)),
        ];
    }

    public function fromCustomerPriceCents(int $customerPriceCents, float $discountAmount = 0, ?float $payoutRate = null): array
    {
        $customerPriceCents = max(0, $customerPriceCents);
        $discountAmountCents = $this->normalizeDiscountAmountCents($discountAmount, PHP_INT_MAX);
        $originalPriceCents = max(0, $customerPriceCents + $discountAmountCents);

        return $this->fromOriginalPriceCents($originalPriceCents, $this->toMoney($discountAmountCents), $payoutRate);
    }

    public function toMoney(int $cents): float
    {
        return round($cents / 100, 2);
    }

    protected function normalizeDiscountAmountCents(float $discountAmount, int $originalPriceCents): int
    {
        $discountAmountCents = max(0, (int) round($discountAmount * 100));

        return min($discountAmountCents, max(0, $originalPriceCents));
    }

    protected function normalizePayoutPercentage(?float $rate): float
    {
        $rate ??= Order::configuredBoosterPayoutPercentage();

        return $rate > 1 ? max(0, $rate) : max(0, $rate * 100);
    }

    protected function percentageToMultiplier(float $percentage): float
    {
        return max(0, $percentage / 100);
    }
}
