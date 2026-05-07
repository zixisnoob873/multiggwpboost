<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait CanonicalizesChatRoute
{
    protected function redirectToCanonicalChatRouteIfNeeded(Request $request, Order $order, string $routeName): ?RedirectResponse
    {
        $incomingOrderReference = (string) ($request->route()?->originalParameter('order') ?? '');
        $canonicalOrderReference = (string) $order->order_number;

        if ($incomingOrderReference === '' || $canonicalOrderReference === '' || $incomingOrderReference === $canonicalOrderReference) {
            return null;
        }

        return redirect()->route($routeName, ['order' => $order], 301);
    }
}
