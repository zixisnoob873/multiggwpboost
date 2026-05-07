<?php

return [
    'modules' => [
        'dashboard' => ['label' => 'Dashboard'],
        'operations' => ['label' => 'Operations'],
        'people' => ['label' => 'People'],
        'marketing' => ['label' => 'Marketing'],
        'content' => ['label' => 'Content'],
        'finance' => ['label' => 'Finance'],
        'system' => ['label' => 'System'],
    ],

    'settings' => [
        'dashboard_notice' => [
            'label' => 'Dashboard Notice',
            'description' => 'Shown in system-facing admin areas for active operational notices.',
            'default' => null,
            'max' => 320,
        ],
        'deployment_notice' => [
            'label' => 'Deployment Notice',
            'description' => 'Short internal note for recent deploys, incidents, or scheduled work.',
            'default' => null,
            'max' => 320,
        ],
    ],
];
