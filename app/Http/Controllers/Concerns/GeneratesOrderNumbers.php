<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Order;
use Illuminate\Support\Str;

trait GeneratesOrderNumbers
{
    protected function generateOrderNumber(): string
    {
        do {
            $number = 'GGWP-' . Str::upper(Str::random(8));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
