<?php

namespace App\Data\Payments;

class PaymentVerificationResult
{
    public function __construct(
        public readonly bool $isPaid,
        public readonly array $updates = []
    ) {}
}
