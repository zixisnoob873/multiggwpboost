<?php

return [
    'enabled' => env('STARTUP_VALIDATION_ENABLED', true),
    'validate_in_console' => env('STARTUP_VALIDATE_IN_CONSOLE', false),
    'skip_environments' => [
        'testing',
    ],
    'strict_integration_environments' => [
        'production',
        'staging',
    ],
    'require_trusted_proxies' => env('STARTUP_REQUIRE_TRUSTED_PROXIES', true),
    'trusted_proxies' => env('TRUSTED_PROXIES'),
];
