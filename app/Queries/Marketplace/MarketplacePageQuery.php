<?php

namespace App\Queries\Marketplace;

use App\Models\Game;
use App\Models\GameService;
use App\Models\ServicePricingRule;
use App\Queries\HomePageContentQuery;
use App\Support\BoostingCatalog;
use App\Support\Cms\PageContentService;
use App\Support\GameCatalog;
use App\Support\Seo\StructuredDataBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MarketplacePageQuery
{
    protected const HOME_FEATURED_GAME_SLUGS = [
        'valorant',
        'league-of-legends',
        'cs2',
        'apex-legends',
        'overwatch-2',
        'black-ops-6',
        'rocket-league',
        'diablo-4',
    ];

    protected const POPULAR_SERVICE_DEFINITIONS = [
        [
            'name' => 'Rank Boosting',
            'kind' => 'rank_boost',
            'description' => 'Move from your current rank to your target division with tracked delivery.',
            'icon' => 'RB',
        ],
        [
            'name' => 'Placement Matches',
            'kind' => 'placement_matches',
            'description' => 'Complete seasonal placements with vetted boosters and clear match counts.',
            'icon' => 'PM',
        ],
        [
            'name' => 'Coaching',
            'kind' => 'coaching',
            'description' => 'Book focused review sessions for mechanics, strategy, and climb planning.',
            'icon' => 'CO',
        ],
        [
            'name' => 'Unlock Services',
            'kind' => 'unlock_services',
            'description' => 'Finish eligible account unlocks, objectives, and progression goals.',
            'icon' => 'UL',
        ],
        [
            'name' => 'Power Leveling',
            'kind' => 'power_leveling',
            'description' => 'Progress levels, seasonal milestones, and endgame-ready account goals.',
            'icon' => 'PL',
        ],
        [
            'name' => 'Battle Pass Completion',
            'kind' => 'battle_pass_completion',
            'description' => 'Finish seasonal tiers faster without losing track of delivery updates.',
            'icon' => 'BP',
        ],
        [
            'name' => 'Weapon Leveling',
            'kind' => 'weapon_leveling',
            'description' => 'Level weapons, unlock attachments, and prepare loadouts with managed delivery.',
            'icon' => 'WL',
        ],
    ];

    protected const WHY_CHOOSE_ITEMS = [
        [
            'title' => 'Professional boosters',
            'body' => 'Orders are handled by vetted players matched to the game, service, and region.',
        ],
        [
            'title' => 'VPN protection',
            'body' => 'Location-aware protection is available for account-shared orders where it applies.',
        ],
        [
            'title' => 'Fast delivery',
            'body' => 'Priority and express options help urgent orders move through assignment quickly.',
        ],
        [
            'title' => 'Secure payments',
            'body' => 'Checkout runs through approved payment providers with order records preserved.',
        ],
        [
            'title' => '24/7 support',
            'body' => 'Live chat and order messaging keep help close before, during, and after checkout.',
        ],
        [
            'title' => 'Custom orders',
            'body' => 'Need a special target? Support can scope custom goals across supported games.',
        ],
    ];

    protected const MARKETPLACE_FAQS = [
        [
            'question' => 'Is boosting safe?',
            'answer' => 'Boosting is handled with careful order scoping, vetted boosters, optional VPN protection, and support visibility. As with any third-party game service, platform rules can vary, so customers should review the terms for their game before ordering.',
        ],
        [
            'question' => 'How fast is delivery?',
            'answer' => 'Delivery depends on the game, rank gap, queue conditions, region, and selected extras. Many standard orders start after checkout and assignment, while priority or express options can shorten the delivery window.',
        ],
        [
            'question' => 'Can I play while boosting?',
            'answer' => 'Yes, when the selected service supports Duo / Self-Play. For account-shared orders, avoid playing on the account during active delivery unless support confirms it is safe to do so.',
        ],
        [
            'question' => 'Do you use VPN?',
            'answer' => 'VPN protection is available on supported account-shared services and is used to keep location handling consistent with the order requirements.',
        ],
    ];

    protected const SUPPORTED_SERVICE_PANES = [
        'rank_boost' => ['partial' => 'boosting', 'tab_id' => 'tab-boosting', 'pane_id' => 'pane-boosting'],
        'placement_matches' => ['partial' => 'placement', 'tab_id' => 'tab-placement', 'pane_id' => 'pane-placement'],
        'radiant_boost' => ['partial' => 'radiant', 'tab_id' => 'tab-radiant', 'pane_id' => 'pane-radiant'],
        'ranked_wins' => ['partial' => 'ranked', 'tab_id' => 'tab-ranked', 'pane_id' => 'pane-ranked'],
    ];

    public function __construct(
        protected GameRepository $games,
        protected ServiceRepository $services,
        protected GameCatalog $gameCatalog,
        protected HomePageContentQuery $homePageContentQuery,
        protected PageContentService $pageContentService,
        protected StructuredDataBuilder $structuredData,
    ) {}

    public function homePage(): array
    {
        $activeGame = $this->gameCatalog->game(GameCatalog::DEFAULT_GAME_SLUG);
        $data = $this->baseHomeData($activeGame, scopeContentToGame: false);
        $seo = $this->marketplaceHomeSeo($this->pageContentService->seo('home'));
        $seo['schema'] = $this->structuredData->home(
            $data['pageContent'],
            $seo,
            $data['marketplaceFaqs'] ?? ($data['faqs'] ?? []),
            $data['latestBlogArticles'] ?? [],
            $this->pageContentService->page('home')?->updated_at,
            $activeGame
        );

        return array_merge($data, [
            'activeGame' => $activeGame,
            'isMarketplaceLanding' => true,
            'seo' => $seo,
        ]);
    }

    public function gamePage(string $gameSlug): ?array
    {
        $game = $this->games->findActiveBySlug($gameSlug);

        if (! $game instanceof Game) {
            return null;
        }

        $activeGame = $this->gameCatalog->game($game->slug);

        if ($activeGame === []) {
            return null;
        }

        $data = $this->baseHomeData($activeGame);
        $seo = $this->homeSeoForGame($activeGame);
        $seo['canonical'] = route('games.show', ['game' => $activeGame['slug']]);
        $seo['schema'] = $this->structuredData->home(
            $data['pageContent'],
            $seo,
            $data['faqs'] ?? [],
            $data['latestBlogArticles'] ?? [],
            $this->pageContentService->page('home')?->updated_at,
            $activeGame
        );

        return array_merge($data, [
            'activeGame' => $activeGame,
            'seo' => $seo,
        ]);
    }

    public function servicePage(string $gameSlug, string $serviceSlug): ?array
    {
        $service = $this->services->servicePageLookup($gameSlug, $serviceSlug);

        if (! $service instanceof GameService || ! ($service->game instanceof Game)) {
            return null;
        }

        $activeGame = $this->gameCatalog->game($service->game->slug);
        $activeService = $this->servicePayloadFromGame($activeGame, $service->slug);

        if ($activeGame === [] || $activeService === []) {
            return null;
        }

        $data = $this->baseHomeData($activeGame, $service->slug);
        $serviceFaqs = $service->relationLoaded('faqs') ? $service->faqs : collect();
        $faqs = $serviceFaqs->isNotEmpty() ? $serviceFaqs : ($data['faqs'] ?? collect());
        $relatedServices = $this->serviceCards($this->services->relatedServices($service));
        $seo = $this->serviceSeoFor($activeGame, $activeService);
        $seo['schema'] = $this->structuredData->servicePage(
            $activeGame,
            $activeService,
            $seo,
            $faqs,
            $relatedServices
        );

        return array_merge($data, [
            'activeGame' => $activeGame,
            'activeService' => $activeService,
            'serviceCalculatorTab' => $this->serviceCalculatorTab($activeService),
            'relatedServices' => $relatedServices,
            'faqs' => $faqs,
            'seo' => $seo,
        ]);
    }

    public function serviceTabs(array $game, ?string $activeServiceSlug = null): array
    {
        $gameSlug = (string) ($game['slug'] ?? GameCatalog::DEFAULT_GAME_SLUG);
        $services = collect(data_get($game, 'services', []));

        if ($services->isEmpty()) {
            $services = collect(BoostingCatalog::serviceOptions())->map(fn (string $serviceName): array => [
                'slug' => Str::slug($serviceName),
                'name' => $serviceName,
                'kind' => Str::slug($serviceName, '_'),
            ]);
        }

        $tabs = $services
            ->map(function (array $service) use ($gameSlug): ?array {
                $kind = (string) data_get($service, 'kind', '');
                $pane = self::SUPPORTED_SERVICE_PANES[$kind] ?? null;
                $serviceSlug = (string) data_get($service, 'slug', '');

                if (! $pane || $serviceSlug === '') {
                    return null;
                }

                return array_merge($pane, [
                    'id' => data_get($service, 'id'),
                    'slug' => $serviceSlug,
                    'name' => data_get($service, 'name'),
                    'kind' => $kind,
                    'description' => data_get($service, 'description'),
                    'url' => route('games.services.show', [
                        'game' => $gameSlug,
                        'service' => $serviceSlug,
                    ]),
                ]);
            })
            ->filter()
            ->values();

        if ($tabs->isEmpty()) {
            return [];
        }

        $activeIndex = $tabs->search(fn (array $tab): bool => $activeServiceSlug !== null && $tab['slug'] === $activeServiceSlug);
        $activeIndex = $activeIndex === false ? 0 : $activeIndex;

        return $tabs
            ->map(fn (array $tab, int $index): array => array_merge($tab, ['active' => $index === $activeIndex]))
            ->all();
    }

    protected function baseHomeData(array $activeGame, ?string $activeServiceSlug = null, bool $scopeContentToGame = true): array
    {
        $gameSlug = (string) ($activeGame['slug'] ?? GameCatalog::DEFAULT_GAME_SLUG);
        $data = $this->homePageContentQuery->execute($scopeContentToGame ? $gameSlug : null);
        $pageContent = $scopeContentToGame
            ? $this->homeContentForGame($activeGame)
            : $this->pageContentService->publicContent('home');

        return array_merge($data, [
            'pageContent' => $pageContent,
            'featuredGames' => $this->homeFeaturedGameCards(),
            'homepageFeaturedServices' => $this->serviceCards($this->services->homepageFeaturedServices(8)),
            'popularServices' => $this->homePopularServiceCardsWithFeaturedPicks(),
            'whyChooseItems' => self::WHY_CHOOSE_ITEMS,
            'marketplaceFaqs' => self::MARKETPLACE_FAQS,
            'marketplaceTagline' => 'GGWPBoost — Premium Boosting Across Every Competitive Game.',
            'serviceTabs' => $this->serviceTabs($activeGame, $activeServiceSlug),
        ]);
    }

    protected function homeContentForGame(array $game): array
    {
        $content = $this->gameLandingContent($game);
        $gameContent = data_get($game, 'metadata.content.home', []);

        if (is_array($gameContent) && $gameContent !== []) {
            $content = array_replace_recursive($content, $gameContent);
        }

        return $this->replaceGameTokens($content, $game);
    }

    protected function gameLandingContent(array $game): array
    {
        $gameShortName = (string) ($game['shortName'] ?? $game['name'] ?? 'VALORANT');

        return [
            'hero' => [
                'eyebrow' => "{$gameShortName} RANK BOOSTING",
                'headline' => "Fast, Safe {$gameShortName} Rank Boosting Built Around Your Goal.",
                'description' => "Configure a {$gameShortName} boost with Solo or Duo / Self-Play options, fair pricing, verified boosters, and live order tracking from start to finish.",
                'primary_cta_label' => "Start My {$gameShortName} Boost",
                'primary_cta_url' => '#servicesTab',
                'secondary_cta_label' => 'Become a Booster',
                'secondary_cta_url' => route('become-booster'),
                'trust_bullets' => [
                    ['text' => 'Verified Boosters'],
                    ['text' => 'Safe Account Handling'],
                    ['text' => 'Live Order Tracking'],
                    ['text' => 'Solo or Duo / Self-Play'],
                ],
            ],
            'how_it_works' => [
                'title' => "How Your {$gameShortName} Boost Works",
                'steps' => [
                    [
                        'title' => '1. Configure Your Boost',
                        'body' => "Choose rank boosting for {$gameShortName}, placement matches, ranked wins, or premium services, then set your ranks, region, platform, and boost mode.",
                    ],
                    [
                        'title' => '2. Customize and Checkout',
                        'body' => 'Pick useful add-ons, choose Solo or Duo / Self-Play where available, and review the live price before secure checkout.',
                    ],
                    [
                        'title' => '3. Track Progress Live',
                        'body' => "Follow your {$gameShortName} boost from the dashboard, receive completion updates, and reach support quickly whenever you need help.",
                    ],
                ],
            ],
            'latest_blogs' => [
                'title' => "{$gameShortName} Boosting Guides",
                'description' => "Fresh guides on {$gameShortName} rank boosting, Duo / Self-Play choices, pricing factors, safety, and smarter ways to climb.",
                'button_label' => "{$gameShortName} Guides",
            ],
        ];
    }

    protected function homeSeoForGame(array $game): array
    {
        $seo = $this->pageContentService->seo('home');
        $gameShortName = (string) ($game['shortName'] ?? $game['name'] ?? 'VALORANT');
        $gameSeo = array_replace(
            (array) data_get($game, 'metadata.seo.home', []),
            (array) data_get($game, 'seo', [])
        );

        $seo['title'] = data_get($gameSeo, 'title', "{$gameShortName} Boosting Services | GGWPBoost");
        $seo['description'] = data_get(
            $gameSeo,
            'description',
            "Compare {$gameShortName} boosting services, live pricing, vetted boosters, and secure checkout."
        );
        $seo['canonical'] = data_get($gameSeo, 'canonical', $seo['canonical'] ?? null);
        $seo['robots'] = data_get($gameSeo, 'robots', $seo['robots'] ?? 'index,follow');
        $seo['type'] = data_get($gameSeo, 'type', $seo['type'] ?? 'website');

        return $seo;
    }

    protected function serviceSeoFor(array $game, array $service): array
    {
        $gameShortName = (string) ($game['shortName'] ?? $game['name'] ?? 'VALORANT');
        $serviceName = (string) ($service['name'] ?? 'Boosting Service');
        $serviceSeo = array_replace(
            (array) data_get($service, 'metadata.seo', []),
            (array) data_get($service, 'seo', [])
        );

        return [
            'title' => data_get($serviceSeo, 'title', "{$gameShortName} {$serviceName} | GGWPBoost"),
            'description' => data_get(
                $serviceSeo,
                'description',
                "Order {$gameShortName} {$serviceName} with secure checkout, clear pricing, vetted boosters, and live support."
            ),
            'canonical' => data_get(
                $serviceSeo,
                'canonical',
                route('games.services.show', ['game' => $game['slug'], 'service' => $service['slug']])
            ),
            'robots' => data_get($serviceSeo, 'robots', 'index,follow'),
            'type' => data_get($serviceSeo, 'type', 'website'),
            'image' => data_get($serviceSeo, 'image'),
        ];
    }

    protected function serviceCalculatorTab(array $service): ?array
    {
        $kind = (string) ($service['kind'] ?? '');
        $pane = self::SUPPORTED_SERVICE_PANES[$kind] ?? null;

        return $pane ? array_merge($pane, $service, ['active' => true]) : null;
    }

    protected function servicePayloadFromGame(array $game, string $serviceSlug): array
    {
        return collect($game['services'] ?? [])
            ->first(fn (array $service): bool => ($service['slug'] ?? null) === $serviceSlug) ?? [];
    }

    protected function gameCards(Collection $games): array
    {
        return $games
            ->map(function (Game $game): array {
                $slug = (string) $game->slug;
                $services = $game->relationLoaded('services')
                    ? $game->services
                    : $game->services()->orderBy('sort_order')->orderBy('id')->get();
                $services = $services
                    ->where('status', Game::STATUS_PUBLISHED)
                    ->values();
                $startingPrice = $this->startingPriceForServices($services);

                return [
                    'id' => $game->id,
                    'slug' => $slug,
                    'name' => $game->name,
                    'shortName' => $game->short_name ?: $game->name,
                    'description' => $game->description,
                    'imageUrl' => $this->gameImageUrl($game),
                    'initials' => $this->initials($game->short_name ?: $game->name),
                    'mainServices' => $services
                        ->pluck('name')
                        ->filter()
                        ->take(3)
                        ->values()
                        ->all(),
                    'startingPrice' => $startingPrice,
                    'startingPriceLabel' => $this->formatStartingPrice($startingPrice),
                    'category' => $game->category ? [
                        'slug' => $game->category->slug,
                        'name' => $game->category->name,
                    ] : null,
                    'url' => route('games.show', ['game' => $slug]),
                    'ctaUrl' => route('checkout', ['game' => $slug]),
                ];
            })
            ->values()
            ->all();
    }

    protected function serviceCards(Collection $services): array
    {
        return $services
            ->filter(fn (GameService $service): bool => $service->game instanceof Game)
            ->map(function (GameService $service): array {
                $game = $service->game;

                return [
                    'id' => $service->id,
                    'slug' => $service->slug,
                    'name' => $service->name,
                    'kind' => $service->kind,
                    'description' => $service->description,
                    'gameSlug' => $game->slug,
                    'gameName' => $game->name,
                    'gameShortName' => $game->short_name ?: $game->name,
                    'url' => route('games.services.show', [
                        'game' => $game->slug,
                        'service' => $service->slug,
                    ]),
                    'ctaUrl' => route('checkout', [
                        'game' => $game->slug,
                        'service' => $service->slug,
                    ]),
                    'startingPrice' => $this->startingPriceForService($service),
                    'startingPriceLabel' => $this->formatStartingPrice($this->startingPriceForService($service)),
                ];
            })
            ->values()
            ->all();
    }

    protected function marketplaceHomeSeo(array $seo): array
    {
        return array_merge($seo, [
            'title' => 'Premium Game Boosting Services for Every Competitive Title',
            'description' => 'Order professional boosting across VALORANT, League of Legends, CS2, Apex Legends, Call of Duty, Overwatch 2, Rocket League, Diablo 4, and more.',
            'canonical' => route('home'),
            'robots' => $seo['robots'] ?? 'index,follow',
            'type' => $seo['type'] ?? 'website',
        ]);
    }

    protected function homeFeaturedGameCards(): array
    {
        $games = $this->games->activeGames();

        if ($games->isEmpty()) {
            return $this->gameCards($this->games->featuredGames(8));
        }

        $ordered = collect(self::HOME_FEATURED_GAME_SLUGS)
            ->map(fn (string $slug): ?Game => $games->first(fn (Game $game): bool => (string) $game->slug === $slug))
            ->filter()
            ->values();

        if ($ordered->count() < 8) {
            $ordered = $ordered
                ->merge($this->games->featuredGames(8)->reject(fn (Game $game): bool => $ordered->contains('id', $game->id)))
                ->take(8)
                ->values();
        }

        return $this->gameCards($ordered);
    }

    protected function homePopularServiceCards(): array
    {
        $services = $this->services->publishedCatalogServices();

        return collect(self::POPULAR_SERVICE_DEFINITIONS)
            ->map(function (array $definition) use ($services): array {
                $kind = Str::slug((string) ($definition['kind'] ?? $definition['name']), '_');
                $service = $services->first(function (GameService $candidate) use ($kind, $definition): bool {
                    return Str::slug((string) $candidate->kind, '_') === $kind
                        || Str::slug((string) $candidate->name) === Str::slug((string) $definition['name']);
                });

                if (! $service instanceof GameService || ! ($service->game instanceof Game)) {
                    return [
                        'id' => null,
                        'slug' => Str::slug((string) $definition['name']),
                        'name' => $definition['name'],
                        'kind' => $kind,
                        'description' => $definition['description'],
                        'gameSlug' => null,
                        'gameName' => 'Multi-game',
                        'gameShortName' => 'Multi-game',
                        'icon' => $definition['icon'],
                        'url' => route('checkout'),
                        'ctaUrl' => route('checkout'),
                        'startingPrice' => null,
                        'startingPriceLabel' => 'Custom quote',
                    ];
                }

                $card = $this->serviceCards(collect([$service]))[0] ?? [];

                return array_merge($card, [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'icon' => $definition['icon'],
                ]);
            })
            ->values()
            ->all();
    }

    protected function homePopularServiceCardsWithFeaturedPicks(): array
    {
        $popular = collect($this->homePopularServiceCards());
        $featured = collect($this->serviceCards($this->services->homepageFeaturedServices(4)))
            ->reject(fn (array $service): bool => $popular->contains('id', $service['id']))
            ->values();

        return $popular
            ->merge($featured)
            ->values()
            ->all();
    }

    protected function startingPriceForServices(Collection $services): ?float
    {
        $prices = $services
            ->map(fn (GameService $service): ?float => $this->startingPriceForService($service))
            ->filter(fn (?float $price): bool => $price !== null && $price > 0)
            ->values();

        return $prices->isEmpty() ? null : (float) $prices->min();
    }

    protected function startingPriceForService(GameService $service): ?float
    {
        $rules = $service->relationLoaded('pricingRules')
            ? $service->pricingRules
            : $service->pricingRules()->active()->get();

        $amounts = collect($rules)
            ->filter(fn (ServicePricingRule $rule): bool => $rule->scope === ServicePricingRule::SCOPE_BASE)
            ->map(fn (ServicePricingRule $rule): ?float => $this->startingPriceForPricingRule($rule))
            ->filter(fn (?float $price): bool => $price !== null && $price > 0)
            ->values();

        if ($amounts->isNotEmpty()) {
            return (float) $amounts->min();
        }

        return $this->fallbackStartingPriceForKind((string) $service->kind);
    }

    protected function startingPriceForPricingRule(ServicePricingRule $rule): ?float
    {
        if ($rule->amount !== null && (float) $rule->amount > 0) {
            return (float) $rule->amount;
        }

        $conditions = $rule->conditions ?? [];
        $source = (string) ($conditions['base_prices_source'] ?? '');

        if ($source !== '') {
            return $this->minNumericValue(config($source));
        }

        return $this->minNumericValue($rule->tiers ?? []);
    }

    protected function fallbackStartingPriceForKind(string $kind): ?float
    {
        return match ($kind) {
            'coaching' => 19.00,
            'weapon_leveling' => 15.00,
            'power_leveling' => 24.00,
            'battle_pass_completion' => 29.00,
            'challenges' => 12.00,
            'farming' => 18.00,
            'rank_boost', 'radiant_boost', 'placement_matches', 'ranked_wins', 'unlock_services', 'faceit_elo', 'predator_boost' => 9.00,
            default => null,
        };
    }

    protected function minNumericValue(mixed $value): ?float
    {
        if (is_numeric($value)) {
            $price = (float) $value;

            return $price > 0 ? $price : null;
        }

        if (! is_array($value) && ! $value instanceof Collection) {
            return null;
        }

        $prices = collect($value)
            ->map(fn (mixed $item): ?float => $this->minNumericValue($item))
            ->filter(fn (?float $price): bool => $price !== null && $price > 0)
            ->values();

        return $prices->isEmpty() ? null : (float) $prices->min();
    }

    protected function formatStartingPrice(?float $price): string
    {
        if ($price === null || $price <= 0) {
            return 'Custom quote';
        }

        $decimals = floor($price) === $price ? 0 : 2;

        return '$'.number_format($price, $decimals);
    }

    protected function initials(?string $value): string
    {
        $words = Str::of((string) $value)
            ->replaceMatches('/[^A-Za-z0-9\s]/', ' ')
            ->squish()
            ->explode(' ')
            ->filter()
            ->values();

        if ($words->isEmpty()) {
            return 'GG';
        }

        if ($words->count() === 1) {
            return Str::of((string) $words->first())->substr(0, 3)->upper()->value();
        }

        return $words
            ->take(2)
            ->map(fn (string $word): string => Str::of($word)->substr(0, 1)->upper()->value())
            ->implode('');
    }

    protected function gameImageUrl(Game $game): ?string
    {
        $asset = collect([
            data_get($game->assets, 'logo_url'),
            data_get($game->assets, 'image_url'),
            data_get($game->assets, 'logo'),
            data_get($game->assets, 'image'),
        ])
            ->map(fn (mixed $value): string => trim((string) $value))
            ->first(fn (string $value): bool => $value !== '');

        if (! $asset) {
            return null;
        }

        if (filter_var($asset, FILTER_VALIDATE_URL)) {
            return $asset;
        }

        return asset(ltrim($asset, '/'));
    }

    protected function replaceGameTokens(mixed $value, array $game): mixed
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $item): mixed => $this->replaceGameTokens($item, $game))
                ->all();
        }

        if (! is_string($value)) {
            return $value;
        }

        $name = (string) ($game['name'] ?? $game['shortName'] ?? 'VALORANT');
        $shortName = (string) ($game['shortName'] ?? $name);

        return str_replace(
            ['VALORANT', 'Valorant', 'valorant'],
            [$shortName, $name, Str::slug($name)],
            $value
        );
    }
}
