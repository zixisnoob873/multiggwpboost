<?php

namespace App\Support;

use App\Models\Game;
use App\Models\GameService;
use App\Queries\Marketplace\GameRepository;
use App\Queries\Marketplace\ServiceCategoryCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MarketplaceNavigation
{
    protected const GAME_GROUPS = [
        'fps' => [
            'label' => 'FPS',
            'aliases' => ['fps', 'tactical-shooter', 'battle-royale', 'hero-shooter', 'arcade-shooter'],
        ],
        'moba' => [
            'label' => 'MOBA',
            'aliases' => ['moba'],
        ],
        'mmo-rpg' => [
            'label' => 'MMO / RPG',
            'aliases' => ['mmo-rpg', 'mmo', 'rpg', 'arpg'],
        ],
    ];

    protected const SERVICE_ITEMS = [
        [
            'label' => 'Rank Boosting',
            'category' => 'rank-boosting',
            'kinds' => ['rank_boost', 'ranked_boosting', 'division_boosting', 'divisions', 'premier_boosting'],
            'slugs' => ['rank-boosting', 'rank-boost', 'division-boosting', 'premier-boosting', 'divisions'],
        ],
        [
            'label' => 'Placements',
            'kinds' => ['placement_matches'],
            'slugs' => ['placement-matches', 'placements'],
        ],
        [
            'label' => 'Coaching',
            'category' => 'coaching',
            'kinds' => ['coaching'],
            'slugs' => ['coaching'],
        ],
        [
            'label' => 'Power Leveling',
            'category' => 'power-leveling',
            'kinds' => ['power_leveling'],
            'slugs' => ['power-leveling'],
        ],
        [
            'label' => 'Unlock Services',
            'category' => 'unlock-services',
            'kinds' => ['unlock_services', 'unlock_all', 'camos', 'camos_unlock_service', 'skin_unlocks', 'operator_unlocks', 'dark_ops', 'calling_cards'],
            'slugs' => ['unlock-services', 'unlock-all', 'camos', 'skin-unlocks', 'operator-unlocks', 'dark-ops', 'calling-cards'],
        ],
        [
            'label' => 'Battle Pass',
            'category' => 'battle-pass',
            'kinds' => ['battle_pass_completion'],
            'slugs' => ['battle-pass-completion', 'battle-pass'],
        ],
        [
            'label' => 'Weapon Leveling',
            'category' => 'weapon-leveling',
            'kinds' => ['weapon_leveling', 'weapon_mastery', 'vehicle_leveling'],
            'slugs' => ['weapon-leveling', 'weapon-mastery', 'vehicle-leveling'],
        ],
        [
            'label' => 'Challenges',
            'kinds' => ['challenges'],
            'slugs' => ['challenges'],
        ],
        [
            'label' => 'Farming',
            'kinds' => ['farming', 'coin_farming', 'blueprint_farming'],
            'slugs' => ['farming', 'coin-farming', 'blueprint-farming'],
        ],
    ];

    public function __construct(
        protected GameRepository $games,
    ) {}

    public function forRequest(Request $request): array
    {
        $games = $this->games->activeGames();
        $currentGameSlug = $this->currentGameSlug($request);
        $currentServiceSlug = $this->currentServiceSlug($request);
        $currentServiceCategorySlug = $this->currentServiceCategorySlug($request);
        $currentService = $this->currentService($games, $currentGameSlug, $currentServiceSlug);
        $gameGroups = $this->gameGroups($games, $request, $currentGameSlug);
        $serviceItems = $this->serviceItems($games, $request, $currentService, $currentServiceCategorySlug);

        return [
            'main' => [
                [
                    'key' => 'games',
                    'label' => 'Games',
                    'active' => $request->routeIs('home', 'game.show', 'games.show'),
                ],
                [
                    'key' => 'services',
                    'label' => 'Services',
                    'active' => $request->routeIs('game.services.show', 'games.services.show', 'services.categories.show'),
                ],
                [
                    'key' => 'reviews',
                    'label' => 'Reviews',
                    'url' => route('reviews'),
                    'active' => $request->routeIs('reviews'),
                ],
                [
                    'key' => 'faq',
                    'label' => 'FAQ',
                    'url' => route('faq'),
                    'active' => $request->routeIs('faq'),
                ],
                [
                    'key' => 'blog',
                    'label' => 'Blog',
                    'url' => route('blog.index'),
                    'active' => $request->routeIs('blog.*'),
                ],
                [
                    'key' => 'contact',
                    'label' => 'Contact',
                    'url' => route('contact'),
                    'active' => $request->routeIs('contact', 'contact.submit'),
                ],
            ],
            'games' => $gameGroups,
            'services' => $serviceItems,
            'ctas' => [
                [
                    'key' => 'order',
                    'label' => 'Order Now',
                    'url' => route('checkout'),
                    'style' => 'primary',
                    'active' => $request->routeIs('checkout'),
                ],
                [
                    'key' => 'chat',
                    'label' => 'Live Chat',
                    'url' => route('contact').'#contactForm',
                    'style' => 'secondary',
                    'active' => false,
                ],
            ],
        ];
    }

    protected function gameGroups(Collection $games, Request $request, ?string $currentGameSlug): array
    {
        $groupedGames = collect(self::GAME_GROUPS)
            ->map(function (array $group, string $key) use ($games, $request, $currentGameSlug): array {
                return [
                    'key' => $key,
                    'label' => $group['label'],
                    'items' => $games
                        ->filter(fn (Game $game): bool => $this->gameGroupKey($game) === $key)
                        ->map(fn (Game $game): array => $this->gameItem($game, $request, $currentGameSlug))
                        ->values()
                        ->all(),
                ];
            })
            ->filter(fn (array $group): bool => $group['items'] !== [])
            ->values()
            ->all();

        if ($groupedGames !== []) {
            return $groupedGames;
        }

        return [
            [
                'key' => 'fps',
                'label' => 'FPS',
                'items' => [
                    [
                        'slug' => GameCatalog::DEFAULT_GAME_SLUG,
                        'name' => 'VALORANT',
                        'shortName' => 'VALORANT',
                        'url' => route('home'),
                        'active' => $request->routeIs('home'),
                    ],
                ],
            ],
        ];
    }

    protected function gameItem(Game $game, Request $request, ?string $currentGameSlug): array
    {
        $slug = (string) $game->slug;
        $current = $request->routeIs('game.show', 'games.show') && $currentGameSlug === $slug;

        return [
            'id' => $game->id,
            'slug' => $slug,
            'name' => $game->name,
            'shortName' => $game->short_name ?: $game->name,
            'url' => route('game.show', ['game' => $slug]),
            'active' => ($request->routeIs('home') && $slug === GameCatalog::DEFAULT_GAME_SLUG)
                || ($currentGameSlug !== null && $slug === $currentGameSlug),
            'current' => $current,
        ];
    }

    protected function serviceItems(Collection $games, Request $request, ?GameService $currentService, ?string $currentServiceCategorySlug): array
    {
        $candidates = $this->serviceCandidates($games);

        return collect(self::SERVICE_ITEMS)
            ->map(function (array $definition) use ($candidates, $request, $currentService, $currentServiceCategorySlug): ?array {
                $candidate = $this->firstServiceMatch($candidates, $definition);

                if ($candidate === null) {
                    return null;
                }

                /** @var Game $game */
                $game = $candidate['game'];
                /** @var GameService $service */
                $service = $candidate['service'];
                $category = isset($definition['category'])
                    ? ServiceCategoryCatalog::find($definition['category'])
                    : null;
                $categorySlug = (string) data_get($category, 'slug', '');
                $url = $category !== null
                    ? (string) data_get($category, 'url')
                    : route('game.services.show', [
                        'game' => $game->slug,
                        'service' => $service->slug,
                    ]);
                $categoryIsCurrent = $categorySlug !== ''
                    && $request->routeIs('services.categories.show')
                    && $currentServiceCategorySlug === $categorySlug;

                return [
                    'label' => $definition['label'],
                    'gameName' => $game->name,
                    'gameShortName' => $category !== null ? 'Category page' : ($game->short_name ?: $game->name),
                    'serviceName' => $service->name,
                    'url' => $url,
                    'serviceUrl' => route('game.services.show', [
                        'game' => $game->slug,
                        'service' => $service->slug,
                    ]),
                    'active' => $categoryIsCurrent || $this->serviceDefinitionIsActive($definition, $request, $currentService),
                    'current' => $categoryIsCurrent || ($request->routeIs('game.services.show', 'games.services.show')
                        && $currentService instanceof GameService
                        && (int) $currentService->id === (int) $service->id),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function serviceCandidates(Collection $games): Collection
    {
        return $games
            ->flatMap(function (Game $game): Collection {
                $services = $game->relationLoaded('services') ? $game->services : $game->services()->get();

                return $services
                    ->where('status', Game::STATUS_PUBLISHED)
                    ->map(fn (GameService $service): array => [
                        'game' => $game,
                        'service' => $service,
                    ]);
            })
            ->values();
    }

    protected function firstServiceMatch(Collection $candidates, array $definition): ?array
    {
        $kinds = collect($definition['kinds'] ?? [])->map(fn (string $kind): string => Str::slug($kind, '_'))->all();
        $slugs = collect($definition['slugs'] ?? [])->map(fn (string $slug): string => Str::slug($slug))->all();

        return $candidates->first(function (array $candidate) use ($kinds, $slugs): bool {
            /** @var GameService $service */
            $service = $candidate['service'];

            return in_array(Str::slug((string) $service->kind, '_'), $kinds, true)
                || in_array(Str::slug((string) $service->slug), $slugs, true);
        });
    }

    protected function serviceDefinitionIsActive(array $definition, Request $request, ?GameService $currentService): bool
    {
        if (! $request->routeIs('game.services.show', 'games.services.show') || ! $currentService instanceof GameService) {
            return false;
        }

        $kinds = collect($definition['kinds'] ?? [])->map(fn (string $kind): string => Str::slug($kind, '_'))->all();
        $slugs = collect($definition['slugs'] ?? [])->map(fn (string $slug): string => Str::slug($slug))->all();

        return in_array(Str::slug((string) $currentService->kind, '_'), $kinds, true)
            || in_array(Str::slug((string) $currentService->slug), $slugs, true);
    }

    protected function currentService(Collection $games, ?string $currentGameSlug, ?string $currentServiceSlug): ?GameService
    {
        if ($currentGameSlug === null || $currentServiceSlug === null) {
            return null;
        }

        $game = $games->first(fn (Game $candidate): bool => (string) $candidate->slug === $currentGameSlug);

        if (! $game instanceof Game) {
            return null;
        }

        $services = $game->relationLoaded('services') ? $game->services : $game->services()->get();

        return $services->first(fn (GameService $service): bool => (string) $service->slug === $currentServiceSlug);
    }

    protected function gameGroupKey(Game $game): ?string
    {
        $categorySlug = $this->normalizeSlug(
            data_get($game->metadata, 'navigation.group')
            ?: data_get($game, 'category.slug')
            ?: data_get($game, 'category.name')
        );

        foreach (self::GAME_GROUPS as $key => $group) {
            if (in_array($categorySlug, $group['aliases'], true)) {
                return $key;
            }
        }

        return null;
    }

    protected function currentGameSlug(Request $request): ?string
    {
        if ($request->routeIs('home')) {
            return GameCatalog::DEFAULT_GAME_SLUG;
        }

        $routeGame = $request->route('game');
        $slug = is_scalar($routeGame) ? $this->normalizeSlug($routeGame) : '';

        return $slug !== '' ? $slug : null;
    }

    protected function currentServiceSlug(Request $request): ?string
    {
        $routeService = $request->route('service');
        $slug = is_scalar($routeService) ? $this->normalizeSlug($routeService) : '';

        return $slug !== '' ? $slug : null;
    }

    protected function currentServiceCategorySlug(Request $request): ?string
    {
        $routeCategory = $request->route('category');
        $slug = is_scalar($routeCategory) ? $this->normalizeSlug($routeCategory) : '';

        return $slug !== '' ? $slug : null;
    }

    protected function normalizeSlug(mixed $value): string
    {
        return Str::slug(trim((string) $value));
    }
}
