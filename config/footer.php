<?php

$supportEmail = env('SUPPORT_EMAIL');
$socialUrl = static function (string $key, string $fallback): string {
    $value = env($key);

    return is_string($value) && trim($value) !== ''
        ? trim($value)
        : $fallback;
};

if (! is_string($supportEmail) || trim($supportEmail) === '') {
    $mailFromAddress = env('MAIL_FROM_ADDRESS');

    $supportEmail = is_string($mailFromAddress) && trim($mailFromAddress) !== ''
        && preg_match('/@example\.(?:com|org|net)$/i', trim($mailFromAddress)) !== 1
            ? trim($mailFromAddress)
            : null;
}

return [
    'brand_copy' => env(
        'FOOTER_BRAND_COPY',
        'Premium game boosting across competitive titles with vetted boosters, clear coordination, and customer-first support from quote to completion.'
    ),

    'company' => [
        'legal_name' => env('FOOTER_LEGAL_NAME', 'GGWP-Boost Limited'),
        'jurisdiction' => env('FOOTER_LEGAL_REGION', 'UK'),
    ],

    'support' => [
        'email' => $supportEmail,
        'phone' => env('SUPPORT_PHONE'),
        'lead' => env(
            'FOOTER_SUPPORT_LEAD',
            'Questions about orders, refunds, or custom requests? Reach our team through the contact form or the Discord community.'
        ),
        'community_url' => env('COMMUNITY_DISCORD_URL', 'https://discord.gg/2FD3qq9U'),
    ],

    'disclaimer' => env(
        'FOOTER_DISCLAIMER',
        'GGWP-Boost provides digital gaming services. Delivery windows, account requirements, and final outcomes can vary based on queue conditions, patch cycles, and the condition of the account supplied for service.'
    ),

    'socials' => [
        [
            'label' => 'Discord',
            'icon' => 'discord.svg',
            'url' => $socialUrl('SOCIAL_DISCORD_URL', env('COMMUNITY_DISCORD_URL', 'https://discord.gg/2FD3qq9U')),
        ],
        [
            'label' => 'Instagram',
            'icon' => 'instagram.svg',
            'url' => $socialUrl('SOCIAL_INSTAGRAM_URL', 'https://www.instagram.com/ggwpboost/'),
        ],
        [
            'label' => 'Facebook',
            'icon' => 'facebook.svg',
            'url' => $socialUrl('SOCIAL_FACEBOOK_URL', 'https://www.facebook.com/ggwpboost'),
        ],
        [
            'label' => 'Twitter',
            'icon' => 'twitter.svg',
            'url' => $socialUrl('SOCIAL_TWITTER_URL', 'https://twitter.com/ggwpboost'),
        ],
        [
            'label' => 'YouTube',
            'icon' => 'youtube.svg',
            'url' => $socialUrl('SOCIAL_YOUTUBE_URL', 'https://www.youtube.com/@ggwpboost'),
        ],
        [
            'label' => 'Twitch',
            'icon' => 'twitch.svg',
            'url' => $socialUrl('SOCIAL_TWITCH_URL', 'https://www.twitch.tv/ggwpboost'),
        ],
    ],
];
