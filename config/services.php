<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'stripe' => [
        'enabled' => (bool) env('STRIPE_ENABLED', true),
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'cryptomus' => [
        'enabled' => (bool) env('CRYPTOMUS_ENABLED', true),
        'merchant_id' => env('CRYPTOMUS_MERCHANT_ID'),
        'api_key' => env('CRYPTOMUS_API_KEY'),
        'base_url' => env('CRYPTOMUS_BASE_URL', 'https://api.cryptomus.com'),
        'allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', env('CRYPTOMUS_ALLOWED_HOSTS', 'api.cryptomus.com'))))),
        'timeout' => (int) env('CRYPTOMUS_TIMEOUT', 15),
        'invoice_lifetime' => (int) env('CRYPTOMUS_INVOICE_LIFETIME', 3600),
    ],

    'booster' => [
        'payout_percentage' => env('BOOSTER_PAYOUT_PERCENTAGE', 60),
    ],

    'discord' => [
        'client_id' => env('DISCORD_CLIENT_ID'),
        'client_secret' => env('DISCORD_CLIENT_SECRET'),
        'redirect' => env('DISCORD_REDIRECT_URI', '/auth/discord/callback'),
        'scopes' => ['identify', 'email'],
        'webhook_orders' => env('DISCORD_ORDER_WEBHOOK_URL'),
        'webhook_order_channels' => array_values(array_unique(array_filter(array_map(
            fn ($url) => trim((string) $url),
            array_merge(
                [env('DISCORD_ORDER_WEBHOOK_URL')],
                explode(',', (string) env('DISCORD_ORDER_WEBHOOK_URLS', ''))
            )
        )))),
        'webhook_booster_applications' => env('DISCORD_BOOSTER_APPLICATION_WEBHOOK_URL'),
        'webhook_contact' => env('DISCORD_CONTACT_WEBHOOK_URL'),
        'webhook_withdrawals' => env('DISCORD_WITHDRAWAL_WEBHOOK_URL'),
        'timeout' => (int) env('DISCORD_WEBHOOK_TIMEOUT', 5),
        'retries' => (int) env('DISCORD_WEBHOOK_RETRIES', 2),
        'retry_sleep_ms' => (int) env('DISCORD_WEBHOOK_RETRY_SLEEP_MS', 250),
    ],

];
