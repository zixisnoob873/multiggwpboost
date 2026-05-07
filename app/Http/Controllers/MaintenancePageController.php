<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class MaintenancePageController extends Controller
{
    public function __invoke(): View
    {
        return view('under-maintenance', [
            'discordUrl' => (string) config('footer.support.community_url', 'https://discord.gg/2FD3qq9U'),
        ]);
    }
}
