<?php

namespace App\Mail\CustomerOrders;

class OrderRefundedCustomerMail extends AbstractCustomerOrderMail
{
    protected function subjectLine(): string
    {
        return 'Order #'.$this->orderReference().' has been refunded';
    }

    protected function viewName(): string
    {
        return 'emails.customer.orders.order-refunded';
    }
}
