# Game asset source register

This register documents the production stance for each seeded MultiGGWPBoost game. The implementation defaults to server-side, cached local assets and generated fallback media. Remote downloads are only allowed through configured providers and allow-listed hosts; no API key is exposed to frontend JavaScript.

| Game | Source stance | Implementation status |
| --- | --- | --- |
| Valorant | Official Riot VALORANT public content catalog/API path exists, but API-key use must remain server-side. | `riot_val_content` provider is server-side and skipped until `RIOT_API_KEY` is configured and production terms are approved. |
| League of Legends | Official Riot Data Dragon static catalog for champions/assets. | `riot_data_dragon` can cache champion portraits locally with `game-assets:sync league-of-legends --provider=riot_data_dragon`. |
| Teamfight Tactics | Official Riot Data Dragon/TFT data exists, but set/champion taxonomy needs project-specific mapping. | Provider resolves Data Dragon version and skips automated sync until TFT mapping is curated. |
| Counter-Strike 2 | No official broad static catalog for ranks/characters confirmed; community APIs exist. | Static fallback; add curated local assets after license review. |
| Apex Legends | Community APIs exist but are unofficial/keyed/no uptime guarantee. | Static fallback; community provider should remain opt-in only. |
| Overwatch 2 | Unofficial APIs scrape/mirror Blizzard data; official press assets are safer for marketing art. | Static fallback; curated local media from permitted press/fan-kit sources. |
| Rainbow Six Siege X | Official operator pages exist; no public static API assumed. | Static fallback; curated local operator media. |
| Call of Duty: Black Ops 6 | Activision press/media pages. | Static fallback; use press assets only when licensed for commercial site use. |
| Call of Duty: Modern Warfare 3 | Activision press/media pages. | Static fallback; use press assets only when licensed for commercial site use. |
| Rocket League | Official Stats API is local match telemetry, not a static asset catalog. | Static fallback. |
| Battlefield 6 | EA newsroom/press multimedia assets. | Static fallback; use licensed press assets. |
| Marvel Rivals | Community APIs exist; no official publisher asset API confirmed. | Static fallback. |
| FragPunk | No official static game asset API confirmed. | Static fallback. |
| Deadlock | Community Deadlock APIs exist; no official Valve asset API confirmed for production use. | Static fallback. |
| Wild Rift | No broad official public asset catalog confirmed. | Static fallback. |
| Heroes of the Storm | Blizzard press/fan-kit style assets. | Static fallback. |
| Diablo 4 | Blizzard press/fan-kit style assets. | Static fallback. |
| New World | No official static game asset API confirmed. | Static fallback. |
| Arc Raiders | Public reporting indicates no current external API support plan; community tools should remain opt-in. | Static fallback. |

## Operational commands

```bash
php artisan game-assets:sync
php artisan game-assets:sync league-of-legends --provider=riot_data_dragon
php artisan game-assets:sync --dry-run
php artisan game-assets:health
```

## Frontend usage rules

- Game cards resolve through `App\Support\Media\GameAssetResolver` before falling back to legacy `games.assets` JSON.
- Rank icons use `window.appState.rankIconMap` when populated by backend; otherwise a local SVG fallback is used.
- Character/agent selectors should read from cached `game_characters` and `game_assets` records as the next backend integration step.
- Never hotlink remote publisher/community images from Blade templates. Cache approved assets locally under `storage/app/public/game-assets`.
