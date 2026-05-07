<?php

namespace App\Contracts\Payments;

use App\Data\Payments\PaymentCheckoutData;
use App\Data\Payments\PaymentInitializationResult;
use App\Data\Payments\PendingCheckout;
use App\Data\Payments\PaymentProviderDescriptor;
use App\Data\Payments\PaymentVerificationResult;

interface PaymentProvider
{
    public function key(): string;

    public function descriptor(): PaymentProviderDescriptor;

    public function initialize(PendingCheckout $pendingCheckout, PaymentCheckoutData $checkoutData): PaymentInitializationResult;

    public function verify(PendingCheckout $pendingCheckout, array $payload = []): PaymentVerificationResult;
}
