<?php

namespace App\Mail\Transactional;

class BoosterAssignedOrderMail extends AbstractTransactionalMail
{
    protected function subjectLine(): string
    {
        $orderNumber = (string) data_get($this->payload, 'order.number', 'your order');

        return "New order assigned: {$orderNumber}";
    }

    protected function viewName(): string
    {
        return 'emails.transactional.booster-assigned-order';
    }
}
