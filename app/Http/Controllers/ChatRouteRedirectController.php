<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;

class ChatRouteRedirectController extends Controller
{
    public function user(Order $order): RedirectResponse
    {
        return redirect()->route('user-chats.show', ['order' => $order], 301);
    }

    public function booster(Order $order): RedirectResponse
    {
        return redirect()->route('booster-chats.show', ['order' => $order], 301);
    }

    public function admin(Order $order): RedirectResponse
    {
        return redirect()->route('admin-chats.show', ['order' => $order], 301);
    }
}
