<?php

return [
    'google' => [
        'measurement_id' => env('ANALYTICS_GOOGLE_MEASUREMENT_ID'),
    ],

    'posthog' => [
        'key' => env('ANALYTICS_POSTHOG_KEY'),
        'host' => rtrim((string) env('ANALYTICS_POSTHOG_HOST', 'https://us.i.posthog.com'), '/'),
        'defaults' => env('ANALYTICS_POSTHOG_DEFAULTS', '2026-01-30'),
    ],
];
