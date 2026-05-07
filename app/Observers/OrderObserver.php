<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\Mail\CustomerOrderEmailNotifier;

class OrderObserver
{
    public function __construct(protected CustomerOrderEmailNotifier $customerOrderEmailNotifier) {}

    public function created(Order $order): void
    {
        $this->customerOrderEmailNotifier->queueCreated($order);
    }

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $this->customerOrderEmailNotifier->queueStatusChanged(
            $order,
            (string) $order->getOriginal('status')
        );
    }
}
