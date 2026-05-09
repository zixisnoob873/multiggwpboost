<?php

return [
    'disk' => env('GAME_ASSET_DISK', 'public'),
    'limit' => (int) env('GAME_ASSET_SYNC_LIMIT', 25),
    'max_bytes' => (int) env('GAME_ASSET_MAX_BYTES', 3145728),
    'allowed_mime_types' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ],
    'fallbacks' => [
        'card' => public_path('assets/game-assets/fallbacks/game-card.svg'),
        'rank_icon' => public_path('assets/game-assets/fallbacks/rank-icon.svg'),
    ],
    'providers' => [
        App\Services\GameAssets\Providers\StaticFallbackProvider::class,
        App\Services\GameAssets\Providers\RiotDataDragonProvider::class,
        App\Services\GameAssets\Providers\ValorantContentProvider::class,
    ],
    'games' => [
        'valorant' => [
            'allowed_hosts' => ['developer.riotgames.com', 'static.developer.riotgames.com', 'media.valorant-api.com'],
            'preferred_provider' => 'riot_val_content',
            'source_notes' => 'Official Riot VALORANT content catalog/API where available; community APIs remain opt-in only.',
        ],
        'league-of-legends' => [
            'allowed_hosts' => ['ddragon.leagueoflegends.com'],
            'preferred_provider' => 'riot_data_dragon',
            'source_notes' => 'Official Riot Data Dragon champion/static assets.',
        ],
        'tft' => [
            'allowed_hosts' => ['ddragon.leagueoflegends.com'],
            'preferred_provider' => 'riot_data_dragon',
            'source_notes' => 'Official Riot Data Dragon TFT files; set taxonomy needs curated mapping.',
        ],
        'cs2' => ['allowed_hosts' => [], 'preferred_provider' => 'static_fallback', 'source_notes' => 'No official broad static asset API confirmed; use curated local assets.'],
        'apex-legends' => ['allowed_hosts' => [], 'preferred_provider' => 'static_fallback', 'source_notes' => 'Unofficial community APIs require key/no uptime guarantee; keep opt-in.'],
        'overwatch-2' => ['allowed_hosts' => ['blizzard.gamespress.com'], 'preferred_provider' => 'static_fallback', 'source_notes' => 'Use official/press assets only after license review; no official public API assumed.'],
        'rainbow-6-siege-x' => ['allowed_hosts' => ['www.ubisoft.com'], 'preferred_provider' => 'static_fallback', 'source_notes' => 'Official operator pages exist; do not scrape into production without review.'],
        'black-ops-6' => ['allowed_hosts' => ['press.activision.com'], 'preferred_provider' => 'static_fallback', 'source_notes' => 'Use Activision press assets only after license review.'],
        'modern-warfare-3' => ['allowed_hosts' => ['press.activision.com'], 'preferred_provider' => 'static_fallback', 'source_notes' => 'Use Activision press assets only after license review.'],
        'rocket-league' => ['allowed_hosts' => [], 'preferred_provider' => 'static_fallback', 'source_notes' => 'Official stats API is local match telemetry, not a public asset catalog.'],
        'battlefield-6' => ['allowed_hosts' => ['news.ea.com'], 'preferred_provider' => 'static_fallback', 'source_notes' => 'EA newsroom/press multimedia requires licensing review before caching.'],
        'marvel-rivals' => ['allowed_hosts' => [], 'preferred_provider' => 'static_fallback', 'source_notes' => 'Community APIs exist; no official publisher asset API confirmed for production.'],
        'fragpunk' => ['allowed_hosts' => [], 'preferred_provider' => 'static_fallback', 'source_notes' => 'No official public asset API confirmed.'],
        'deadlock' => ['allowed_hosts' => [], 'preferred_provider' => 'static_fallback', 'source_notes' => 'Community Deadlock APIs exist but are not official Valve sources.'],
        'wild-rift' => ['allowed_hosts' => [], 'preferred_provider' => 'static_fallback', 'source_notes' => 'No broad official public asset catalog confirmed.'],
        'heroes-of-the-storm' => ['allowed_hosts' => ['blizzard.gamespress.com'], 'preferred_provider' => 'static_fallback', 'source_notes' => 'Blizzard press assets only after license review.'],
        'diablo-4' => ['allowed_hosts' => ['blizzard.gamespress.com'], 'preferred_provider' => 'static_fallback', 'source_notes' => 'Blizzard press assets only after license review.'],
        'new-world' => ['allowed_hosts' => [], 'preferred_provider' => 'static_fallback', 'source_notes' => 'No official public asset API confirmed.'],
        'arc-raiders' => ['allowed_hosts' => [], 'preferred_provider' => 'static_fallback', 'source_notes' => 'No current official external API plan identified; keep static fallback.'],
    ],
];
