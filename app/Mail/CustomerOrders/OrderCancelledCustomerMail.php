<?php

namespace App\Mail\CustomerOrders;

class OrderCancelledCustomerMail extends AbstractCustomerOrderMail
{
    protected function subjectLine(): string
    {
        return 'Order #'.$this->orderReference().' has been cancelled';
    }

    protected function viewName(): string
    {
        return 'emails.customer.orders.order-cancelled';
    }
}
