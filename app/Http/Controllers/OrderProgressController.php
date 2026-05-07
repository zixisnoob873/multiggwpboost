<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOrderProgressRequest;
use App\Models\Order;
use App\Services\Orders\OrderProgressService;
use Illuminate\Http\RedirectResponse;

class OrderProgressController extends Controller
{
    public function __construct(protected OrderProgressService $orderProgressService) {}

    public function update(UpdateOrderProgressRequest $request, Order $order): RedirectResponse
    {
        $this->orderProgressService->update($order, $request->user(), $request->validated());

        return back()->with('status', 'Progress updated.');
    }
}
