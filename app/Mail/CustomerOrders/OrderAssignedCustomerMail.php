<?php

namespace App\Mail\CustomerOrders;

class OrderAssignedCustomerMail extends AbstractCustomerOrderMail
{
    protected function subjectLine(): string
    {
        return 'Order #'.$this->orderReference().' is now in progress';
    }

    protected function viewName(): string
    {
        return 'emails.customer.orders.order-assigned';
    }
}
