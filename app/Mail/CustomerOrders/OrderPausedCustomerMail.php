<?php

namespace App\Mail\CustomerOrders;

class OrderPausedCustomerMail extends AbstractCustomerOrderMail
{
    protected function subjectLine(): string
    {
        return 'Order #'.$this->orderReference().' is currently paused';
    }

    protected function viewName(): string
    {
        return 'emails.customer.orders.order-paused';
    }
}
