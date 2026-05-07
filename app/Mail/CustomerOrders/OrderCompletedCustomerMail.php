<?php

namespace App\Mail\CustomerOrders;

class OrderCompletedCustomerMail extends AbstractCustomerOrderMail
{
    protected function subjectLine(): string
    {
        return 'Order #'.$this->orderReference().' is complete';
    }

    protected function viewName(): string
    {
        return 'emails.customer.orders.order-completed';
    }
}
