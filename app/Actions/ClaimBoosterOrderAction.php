<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderAssignmentService;
use App\Support\Logging\AppEventLogger;

class ClaimBoosterOrderAction
{
    public function __construct(
        protected OrderAssignmentService $orderAssignmentService,
        protected AppEventLogger $eventLogger,
    ) {}

    public function execute(User $user, Order $order): Order
    {
        $claimedOrder = $this->orderAssignmentService->claim($user, $order);
        $this->eventLogger->order('order.booster_claimed', $claimedOrder, $user);

        return $claimedOrder;
    }
}
