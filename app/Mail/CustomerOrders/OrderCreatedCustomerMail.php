<?php

namespace App\Mail\CustomerOrders;

class OrderCreatedCustomerMail extends AbstractCustomerOrderMail
{
    protected function subjectLine(): string
    {
        return 'Order #'.$this->orderReference().' confirmed';
    }

    protected function viewName(): string
    {
        return 'emails.customer.orders.order-created';
    }
}
