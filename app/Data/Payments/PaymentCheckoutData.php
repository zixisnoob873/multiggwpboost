<?php

namespace App\Data\Payments;

class PaymentCheckoutData
{
    public function __construct(
        public readonly array $requestData,
        public readonly array $orderPayload,
        public readonly string $paymentMethod,
        public readonly int $priceCents,
        public readonly float $total,
        public readonly float $subtotal = 0.0,
        public readonly ?int $promoCodeId = null,
        public readonly ?string $promoCode = null,
        public readonly float $discountAmount = 0.0,
        public readonly array $baseOrderPayload = [],
        public readonly array $metadata = [],
    ) {}
}
