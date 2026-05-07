<?php

return [
    'pending_checkouts' => [
        'ttl_hours' => (int) env('PENDING_CHECKOUT_TTL_HOURS', 24),
        'stale_retention_hours' => (int) env('PENDING_CHECKOUT_STALE_RETENTION_HOURS', 24 * 7),
        'completed_retention_hours' => (int) env('PENDING_CHECKOUT_COMPLETED_RETENTION_HOURS', 24 * 30),
    ],

    'webhooks' => [
        'processing_timeout_minutes' => (int) env('WEBHOOK_PROCESSING_TIMEOUT_MINUTES', 2),
    ],

    'discord' => [
        'queue' => env('DISCORD_NOTIFICATIONS_QUEUE', 'notifications'),
        'retry_failed_after_minutes' => (int) env('DISCORD_RETRY_FAILED_AFTER_MINUTES', 10),
        'public_form_dedupe_window_minutes' => (int) env('DISCORD_PUBLIC_FORM_DEDUPE_WINDOW_MINUTES', 15),
    ],

    'customer_order_emails' => [
        'queue' => env('CUSTOMER_ORDER_EMAILS_QUEUE', 'notifications'),
        'retry_failed_after_minutes' => (int) env('CUSTOMER_ORDER_EMAIL_RETRY_FAILED_AFTER_MINUTES', 10),
    ],
];
