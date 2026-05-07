<?php

namespace App\Services\Payments;

use App\Actions\CreateOrderAction;
use App\Data\Payments\PendingCheckout;
use App\Models\Order;
use App\Services\Orders\RankTrackerActionService;

class PendingCheckoutFulfillmentService
{
    public function __construct(
        protected CreateOrderAction $createOrderAction,
        protected RankTrackerActionService $rankTrackerActionService,
    ) {}

    public function fulfill(PendingCheckout $pendingCheckout, string $providerKey, array $paymentAttributes = []): Order
    {
        return match ((string) ($pendingCheckout->metadata['checkoutKind'] ?? 'default')) {
            'order_extension' => $this->rankTrackerActionService->fulfillExtension($pendingCheckout, $providerKey, $paymentAttributes),
            'order_tip_booster', 'order_tip_admin' => $this->rankTrackerActionService->fulfillTip($pendingCheckout, $providerKey, $paymentAttributes),
            default => $this->createOrderAction->execute(
                $pendingCheckout->userId,
                $pendingCheckout->toCheckoutData($providerKey),
                $paymentAttributes,
            ),
        };
    }
}
