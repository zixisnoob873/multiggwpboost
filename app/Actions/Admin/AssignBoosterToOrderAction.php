<?php

namespace App\Actions\Admin;

use App\Models\Order;
use App\Services\OrderAssignmentService;

class AssignBoosterToOrderAction
{
    public function __construct(protected OrderAssignmentService $orderAssignmentService) {}

    public function execute(Order $order, ?int $boosterId): Order
    {
        return $this->orderAssignmentService->assignByAdmin($order, $boosterId);
    }
}
