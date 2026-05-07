<?php

namespace App\Services\Payments;

use App\Data\Payments\PaymentCheckoutData;
use App\Data\Payments\PaymentInitializationResult;
use App\Data\Payments\PendingCheckout;
use LogicException;

class PaymentInitializationPipeline
{
    public function __construct(
        protected PaymentManager $paymentManager,
        protected FinalizePendingCheckoutService $finalizePendingCheckoutService,
    ) {}

    public function initialize(PendingCheckout $pendingCheckout, PaymentCheckoutData $checkoutData): PaymentInitializationResult
    {
        if ($checkoutData->priceCents <= 0 || $checkoutData->total <= 0) {
            $order = $this->finalizePendingCheckoutService->finalizeFree($pendingCheckout);

            return PaymentInitializationResult::route('user-chats.show', ['order' => $order]);
        }

        $provider = $this->paymentManager->provider($checkoutData->paymentMethod);
        $result = $provider->initialize($pendingCheckout, $checkoutData);

        if ($result->type === 'handoff') {
            throw new LogicException('Payment provider handoff is disabled.');
        }

        return $result;
    }
}
