<?php

namespace App\Mail\CustomerOrders;

class OrderResumedCustomerMail extends AbstractCustomerOrderMail
{
    protected function subjectLine(): string
    {
        return 'Order #'.$this->orderReference().' has resumed';
    }

    protected function viewName(): string
    {
        return 'emails.customer.orders.order-resumed';
    }
}
