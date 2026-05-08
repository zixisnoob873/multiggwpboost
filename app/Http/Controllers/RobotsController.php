<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /admin/',
            'Disallow: /user',
            'Disallow: /user/',
            'Disallow: /booster',
            'Disallow: /booster/',
            'Disallow: /boost',
            'Disallow: /boost/',
            'Disallow: /orders',
            'Disallow: /orders/',
            'Disallow: /api/',
            '',
            'Sitemap: '.route('sitemap'),
            '',
        ];

        return response(implode("\n", $lines), 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
