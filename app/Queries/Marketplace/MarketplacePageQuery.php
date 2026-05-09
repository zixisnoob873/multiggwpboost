<?php

namespace App\Queries\Marketplace;

use App\Models\BlogArticle;
use App\Models\Faq;
use App\Models\Game;
use App\Models\GameAddon;
use App\Models\GameCategory;
use App\Models\GameService;
use App\Models\Review;
use App\Models\ServicePricingRule;
use App\Queries\HomePageContentQuery;
use App\Support\BoostingCatalog;
use App\Support\Cms\PageContentService;
use App\Support\GameCatalog;
use App\Support\Seo\MarketplaceSeo;
use App\Support\Seo\StructuredDataBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
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

    protected const MARKETPLACE_REVIEWS = [
        [
            'author_name' => 'Verified Customer',
            'service' => 'Rank Boosting',
            'quote' => 'Fast communication, clear order tracking, and a smooth delivery from checkout to completion.',
        ],
        [
            'author_name' => 'Returning Customer',
            'service' => 'Placement Matches',
            'quote' => 'The flow was simple, support answered quickly, and every update was easy to follow.',
        ],
        [
            'author_name' => 'Multi-game Customer',
            'service' => 'Power Leveling',
            'quote' => 'Professional handling, secure payment, and reliable progress updates across the whole order.',
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
        protected MarketplaceSeo $marketplaceSeo,
    ) {}

    public function homePage(): array
    {
        $activeGame = $this->gameCatalog->game(GameCatalog::DEFAULT_GAME_SLUG);
        $data = $this->baseHomeData($activeGame, scopeContentToGame: false);
        $seo = $this->marketplaceSeo->home($this->pageContentService->seo('home'));
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
            'breadcrumbs' => [],
        ]);
    }

    public function gamePage(string $gameSlug): ?array
    {
        $game = $this->games->findActiveBySlug($gameSlug);

        if (! $game instanceof Game) {
            return null;
        }

        $activeGame = $this->withCategoryUrl($this->gameCatalog->game($game->slug));

        if ($activeGame === []) {
            return null;
        }

        $data = $this->baseHomeData($activeGame);
        $seo = $this->marketplaceSeo->game($activeGame);
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
            'breadcrumbs' => $this->gameBreadcrumbs($activeGame),
        ]);
    }

    public function gameLandingPage(string $gameSlug): ?array
    {
        $game = $this->games->findActiveBySlug($gameSlug);

        if (! $game instanceof Game) {
            return null;
        }

        $activeGame = $this->withCategoryUrl($this->gameCatalog->game($game->slug));

        if ($activeGame === []) {
            return null;
        }

        $services = $this->publishedServicesForGame($game);
        $serviceCards = $this->serviceCards($services, singularRoutes: true);
        $gameCard = $this->gameCards(collect([$game]), singularRoutes: true)[0] ?? [];
        $faqs = $this->resolvedFaqsForGame($game, $activeGame);
        $reviews = $this->resolvedReviewsForGame($game, $activeGame);
        $orderSteps = $this->gameOrderProcessSteps($activeGame);
        $seo = $this->marketplaceSeo->game($activeGame);
        $seo['schema'] = $this->structuredData->gamePage(
            $activeGame,
            $serviceCards,
            $seo,
            $faqs,
            $reviews,
            $orderSteps
        );

        return [
            'activeGame' => $activeGame,
            'gameCard' => $gameCard,
            'gameServices' => $serviceCards,
            'whyChooseItems' => self::WHY_CHOOSE_ITEMS,
            'orderProcessSteps' => $orderSteps,
            'faqs' => $faqs,
            'reviews' => $reviews,
            'relatedServices' => $this->relatedServiceCardsForGame($game, $services),
            'seo' => $seo,
            'breadcrumbs' => $this->gameBreadcrumbs($activeGame),
        ];
    }

    public function categoryPage(string $categorySlug): ?array
    {
        $category = $this->games->findActiveCategoryBySlug($categorySlug);

        if (! $category instanceof GameCategory) {
            return null;
        }

        $games = $category->relationLoaded('games') ? $category->games : $category->games()->get();
        $games = $games
            ->where('status', Game::STATUS_PUBLISHED)
            ->sortBy(fn (Game $game): string => sprintf('%08d-%s', (int) $game->sort_order, (string) $game->name))
            ->values();
        $gameCards = $this->gameCards($games, singularRoutes: true);
        $services = $games
            ->flatMap(fn (Game $game): Collection => $this->publishedServicesForGame($game))
            ->values();
        $serviceCards = $this->serviceCards($services, singularRoutes: true);
        $seo = $this->marketplaceSeo->category($category, $games);
        $seo['schema'] = $this->structuredData->categoryPage($category, $gameCards, $serviceCards, $seo);

        return [
            'category' => $category,
            'categoryGames' => $gameCards,
            'categoryServices' => $serviceCards,
            'faqs' => $this->resolvedGlobalFaqs(),
            'reviews' => $this->resolvedGlobalReviews(),
            'seo' => $seo,
            'breadcrumbs' => $this->categoryBreadcrumbs($category),
        ];
    }

    public function serviceCategoryPage(string $categorySlug): ?array
    {
        $category = ServiceCategoryCatalog::find($categorySlug);

        if ($category === null) {
            return null;
        }

        $services = $this->services->servicesForCategory($category);

        if ($services->isEmpty()) {
            return null;
        }

        $games = $services
            ->pluck('game')
            ->filter(fn (mixed $game): bool => $game instanceof Game)
            ->unique('id')
            ->sortBy(fn (Game $game): string => sprintf('%08d-%s', (int) $game->sort_order, (string) $game->name))
            ->values();
        $serviceCards = collect($this->serviceCards($services, singularRoutes: true))
            ->map(fn (array $service): array => array_merge($service, [
                'ctaUrl' => $service['url'] ?? route('home'),
            ]))
            ->values()
            ->all();
        $gameCards = $this->gameCards($games, singularRoutes: true);
        $startingPrice = $this->startingPriceForServices($services);
        $category = array_merge($category, [
            'startingPrice' => $startingPrice,
            'startingPriceLabel' => $this->formatStartingPrice($startingPrice),
            'gameCount' => $games->count(),
            'serviceCount' => $services->count(),
        ]);
        $relatedCategories = $this->relatedServiceCategoryCards((string) $category['slug']);
        $faqs = $this->serviceCategoryFaqs($category);
        $reviews = $this->serviceCategoryReviews($category, collect($serviceCards));
        $seo = $this->marketplaceSeo->serviceCategory($category, $games, $services);
        $seo['schema'] = $this->structuredData->serviceCategoryPage(
            $category,
            $gameCards,
            $serviceCards,
            $faqs,
            $relatedCategories,
            $seo
        );

        return [
            'serviceCategory' => $category,
            'categoryGames' => $gameCards,
            'categoryServices' => $serviceCards,
            'faqs' => $faqs,
            'reviews' => $reviews,
            'relatedServiceCategories' => $relatedCategories,
            'seo' => $seo,
            'breadcrumbs' => $this->serviceCategoryBreadcrumbs($category),
        ];
    }

    public function servicePage(string $gameSlug, string $serviceSlug): ?array
    {
        $service = $this->services->servicePageLookup($gameSlug, $serviceSlug);

        if (! $service instanceof GameService || ! ($service->game instanceof Game)) {
            return null;
        }

        $activeGame = $this->withCategoryUrl($this->gameCatalog->game($service->game->slug));
        $activeService = $this->servicePayloadFromGame($activeGame, $service->slug);

        if ($activeGame === [] || $activeService === []) {
            return null;
        }

        $data = $this->baseHomeData($activeGame, $service->slug);
        $faqs = $this->resolvedFaqsForService($service, $activeGame);
        $reviews = $this->resolvedReviewsForService($service, $activeGame);
        $relatedServices = $this->serviceCards($this->services->relatedServices($service));
        $serviceCard = $this->serviceCards(collect([$service]), singularRoutes: true)[0] ?? [];
        $serviceAddons = $this->servicePageAddons($activeGame, $activeService, $service);
        $orderSteps = $this->serviceOrderProcessSteps($activeGame, $activeService);
        $seo = $this->marketplaceSeo->service($activeGame, $activeService);
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
            'serviceCard' => $serviceCard,
            'serviceHero' => $this->serviceHero($activeGame, $activeService, $serviceCard, $reviews),
            'serviceCalculator' => $this->serviceCalculatorConfig($activeGame, $activeService, $serviceCard, $serviceAddons),
            'serviceAddons' => $serviceAddons,
            'estimatedDelivery' => $this->estimatedDelivery($activeService),
            'orderProcessSteps' => $orderSteps,
            'serviceCalculatorTab' => $this->serviceCalculatorTab($activeService),
            'relatedServices' => $relatedServices,
            'relatedBlogArticles' => $this->relatedBlogArticles($service),
            'faqs' => $faqs,
            'reviews' => $reviews,
            'seo' => $seo,
            'breadcrumbs' => $this->serviceBreadcrumbs($activeGame, $activeService),
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
                    'url' => route('game.services.show', [
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
        $game = $scopeContentToGame ? $this->games->findBySlug($gameSlug) : null;
        $faqs = $game instanceof Game
            ? $this->resolvedFaqsForGame($game, $activeGame)
            : $this->resolvedGlobalFaqs();
        $reviews = $game instanceof Game
            ? $this->resolvedReviewsForGame($game, $activeGame)
            : $this->resolvedGlobalReviews();

        return array_merge($data, [
            'pageContent' => $pageContent,
            'featuredGames' => $this->homeFeaturedGameCards(),
            'homepageFeaturedServices' => $this->serviceCards($this->services->homepageFeaturedServices(8)),
            'popularServices' => $this->homePopularServiceCardsWithFeaturedPicks(),
            'whyChooseItems' => self::WHY_CHOOSE_ITEMS,
            'faqs' => $faqs,
            'reviews' => $reviews,
            'marketplaceFaqs' => $this->resolvedGlobalFaqs(),
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

    protected function gameLandingSeoFor(array $game, array $gameCard): array
    {
        $gameShortName = (string) ($game['shortName'] ?? $game['name'] ?? 'Game');
        $gameName = (string) ($game['name'] ?? $gameShortName);
        $gameSeo = (array) data_get($game, 'seo', []);

        return [
            'title' => data_get($gameSeo, 'title', "{$gameName} Boosting Services | GGWPBoost"),
            'description' => data_get(
                $gameSeo,
                'description',
                data_get(
                    $game,
                    'description',
                    "Order {$gameName} boosting services with professional boosters, secure checkout, clear pricing, and live support."
                )
            ),
            'canonical' => data_get($gameSeo, 'canonical', route('game.show', ['game' => $game['slug']])),
            'robots' => data_get($gameSeo, 'robots', 'index,follow'),
            'type' => data_get($gameSeo, 'type', 'website'),
            'image' => data_get($gameSeo, 'image', data_get($gameCard, 'imageUrl')),
        ];
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
                route('game.services.show', ['game' => $game['slug'], 'service' => $service['slug']])
            ),
            'robots' => data_get($serviceSeo, 'robots', 'index,follow'),
            'type' => data_get($serviceSeo, 'type', 'website'),
            'image' => data_get($serviceSeo, 'image'),
        ];
    }

    protected function withCategoryUrl(array $game): array
    {
        $categorySlug = (string) data_get($game, 'category.slug', '');

        if ($categorySlug !== '') {
            data_set($game, 'category.url', route('games.categories.show', ['category' => $categorySlug]));
        }

        return $game;
    }

    protected function categoryBreadcrumbs(GameCategory $category): array
    {
        return [
            ['name' => 'Home', 'url' => route('home')],
            ['name' => $category->name, 'url' => route('games.categories.show', ['category' => $category->slug])],
        ];
    }

    protected function gameBreadcrumbs(array $game): array
    {
        $breadcrumbs = [
            ['name' => 'Home', 'url' => route('home')],
        ];

        $categorySlug = (string) data_get($game, 'category.slug', '');
        $categoryName = (string) data_get($game, 'category.name', '');

        if ($categorySlug !== '' && $categoryName !== '') {
            $breadcrumbs[] = [
                'name' => $categoryName,
                'url' => route('games.categories.show', ['category' => $categorySlug]),
            ];
        }

        $breadcrumbs[] = [
            'name' => (string) data_get($game, 'shortName', data_get($game, 'name', 'Game')),
            'url' => route('game.show', ['game' => data_get($game, 'slug', GameCatalog::DEFAULT_GAME_SLUG)]),
        ];

        return $breadcrumbs;
    }

    protected function serviceBreadcrumbs(array $game, array $service): array
    {
        return [
            ...$this->gameBreadcrumbs($game),
            [
                'name' => (string) data_get($service, 'name', 'Service'),
                'url' => route('game.services.show', [
                    'game' => data_get($game, 'slug', GameCatalog::DEFAULT_GAME_SLUG),
                    'service' => data_get($service, 'slug', 'service'),
                ]),
            ],
        ];
    }

    protected function serviceCategoryBreadcrumbs(array $category): array
    {
        return [
            ['name' => 'Home', 'url' => route('home')],
            [
                'name' => (string) data_get($category, 'name', 'Services'),
                'url' => route('services.categories.show', [
                    'category' => data_get($category, 'slug', 'rank-boosting'),
                ]),
            ],
        ];
    }

    protected function relatedServiceCategoryCards(string $currentSlug): array
    {
        return collect(ServiceCategoryCatalog::related($currentSlug))
            ->filter(fn (array $definition): bool => $this->services->servicesForCategory($definition)->isNotEmpty())
            ->map(function (array $definition): array {
                $services = $this->services->servicesForCategory($definition);
                $startingPrice = $this->startingPriceForServices($services);

                return [
                    'slug' => $definition['slug'],
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'url' => $definition['url'],
                    'serviceCount' => $services->count(),
                    'startingPriceLabel' => $this->formatStartingPrice($startingPrice),
                ];
            })
            ->values()
            ->all();
    }

    protected function resolvedGlobalFaqs(): Collection
    {
        $items = $this->globalFaqs();

        return $items->isNotEmpty() ? $items : collect(self::MARKETPLACE_FAQS);
    }

    protected function resolvedGlobalReviews(): Collection
    {
        $items = $this->globalReviews();

        return $items->isNotEmpty() ? $items : collect(self::MARKETPLACE_REVIEWS);
    }

    protected function resolvedFaqsForGame(Game $game, array $activeGame): Collection
    {
        $items = $this->gameFaqs($game);

        if ($items->isNotEmpty()) {
            return $items;
        }

        $global = $this->globalFaqs();

        return $global->isNotEmpty() ? $global : $this->gamePageFaqs(collect(), $activeGame);
    }

    protected function resolvedReviewsForGame(Game $game, array $activeGame): Collection
    {
        $items = $this->gameReviews($game);

        if ($items->isNotEmpty()) {
            return $items;
        }

        $global = $this->globalReviews();

        return $global->isNotEmpty() ? $global : $this->gamePageReviews(collect(), $activeGame);
    }

    protected function resolvedFaqsForService(GameService $service, array $activeGame): Collection
    {
        $items = $this->serviceFaqs($service);

        if ($items->isNotEmpty()) {
            return $items;
        }

        if ($service->game instanceof Game) {
            return $this->resolvedFaqsForGame($service->game, $activeGame);
        }

        return $this->resolvedGlobalFaqs();
    }

    protected function resolvedReviewsForService(GameService $service, array $activeGame): Collection
    {
        $items = $this->serviceReviews($service);

        if ($items->isNotEmpty()) {
            return $items;
        }

        if ($service->game instanceof Game) {
            return $this->resolvedReviewsForGame($service->game, $activeGame);
        }

        return $this->resolvedGlobalReviews();
    }

    protected function globalFaqs(): Collection
    {
        if (! Schema::hasTable('faqs')) {
            return collect();
        }

        $query = Faq::query()->orderBy('order')->orderBy('id');

        if (Schema::hasColumn('faqs', 'game_id')) {
            $query->whereNull('game_id');
        }

        if (Schema::hasColumn('faqs', 'service_id')) {
            $query->whereNull('service_id');
        }

        return $query->get($this->faqColumns());
    }

    protected function gameFaqs(Game $game): Collection
    {
        if (! Schema::hasTable('faqs') || ! Schema::hasColumn('faqs', 'game_id')) {
            return collect();
        }

        $query = Faq::query()
            ->where('game_id', $game->id)
            ->orderBy('order')
            ->orderBy('id');

        if (Schema::hasColumn('faqs', 'service_id')) {
            $query->whereNull('service_id');
        }

        return $query->get($this->faqColumns());
    }

    protected function serviceFaqs(GameService $service): Collection
    {
        if (! Schema::hasTable('faqs') || ! Schema::hasColumn('faqs', 'service_id')) {
            return collect();
        }

        $query = Faq::query()
            ->where('service_id', $service->id)
            ->orderBy('order')
            ->orderBy('id');

        if (Schema::hasColumn('faqs', 'game_id')) {
            $query->where('game_id', $service->game_id);
        }

        return $query->get($this->faqColumns());
    }

    protected function globalReviews(): Collection
    {
        if (! Schema::hasTable('testimonials')) {
            return collect();
        }

        $query = Review::query()->orderBy('sort_order')->orderByDesc('id')->limit(6);

        if (Schema::hasColumn('testimonials', 'game_id')) {
            $query->whereNull('game_id');
        }

        if (Schema::hasColumn('testimonials', 'service_id')) {
            $query->whereNull('service_id');
        }

        return $query->get($this->reviewColumns());
    }

    protected function gameReviews(Game $game): Collection
    {
        if (! Schema::hasTable('testimonials') || ! Schema::hasColumn('testimonials', 'game_id')) {
            return collect();
        }

        $query = Review::query()
            ->where('game_id', $game->id)
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit(6);

        if (Schema::hasColumn('testimonials', 'service_id')) {
            $query->whereNull('service_id');
        }

        return $query->get($this->reviewColumns());
    }

    protected function serviceReviews(GameService $service): Collection
    {
        if (! Schema::hasTable('testimonials') || ! Schema::hasColumn('testimonials', 'service_id')) {
            return collect();
        }

        $query = Review::query()
            ->where('service_id', $service->id)
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit(6);

        if (Schema::hasColumn('testimonials', 'game_id')) {
            $query->where('game_id', $service->game_id);
        }

        return $query->get($this->reviewColumns());
    }

    protected function faqColumns(): array
    {
        $columns = ['question', 'answer'];

        if (Schema::hasColumn('faqs', 'game_id')) {
            $columns[] = 'game_id';
        }

        if (Schema::hasColumn('faqs', 'service_id')) {
            $columns[] = 'service_id';
        }

        return $columns;
    }

    protected function reviewColumns(): array
    {
        $columns = ['author_name', 'service', 'quote', 'sort_order'];

        if (Schema::hasColumn('testimonials', 'game_id')) {
            $columns[] = 'game_id';
        }

        if (Schema::hasColumn('testimonials', 'service_id')) {
            $columns[] = 'service_id';
        }

        return $columns;
    }

    protected function serviceCategoryFaqs(array $category): Collection
    {
        return collect(data_get($category, 'faqs', []))
            ->map(fn (array $faq): array => [
                'question' => $this->replaceServiceCategoryTokens((string) ($faq['question'] ?? ''), $category),
                'answer' => $this->replaceServiceCategoryTokens((string) ($faq['answer'] ?? ''), $category),
            ])
            ->filter(fn (array $faq): bool => $faq['question'] !== '' && $faq['answer'] !== '')
            ->values();
    }

    protected function serviceCategoryReviews(array $category, Collection $serviceCards): Collection
    {
        $categoryName = (string) data_get($category, 'name', 'service');

        return $serviceCards
            ->take(3)
            ->values()
            ->map(function (array $service, int $index) use ($categoryName): array {
                $authors = ['Verified Customer', 'Returning Customer', 'Competitive Player'];
                $gameShortName = (string) data_get($service, 'gameShortName', 'Game');
                $serviceName = (string) data_get($service, 'name', $categoryName);

                return [
                    'author_name' => $authors[$index] ?? 'Verified Customer',
                    'service' => "{$gameShortName} {$serviceName}",
                    'quote' => "The {$categoryName} category made it easy to compare {$gameShortName} options and open the exact service before checkout.",
                ];
            });
    }

    protected function replaceServiceCategoryTokens(string $value, array $category): string
    {
        return str_replace(
            ['{category}', '{starting_price}', '{game_count}', '{service_count}'],
            [
                (string) data_get($category, 'name', 'this service'),
                (string) data_get($category, 'startingPriceLabel', 'Custom quote'),
                (string) data_get($category, 'gameCount', 0),
                (string) data_get($category, 'serviceCount', 0),
            ],
            $value
        );
    }

    protected function relatedBlogArticles(GameService $service, int $limit = 3): Collection
    {
        if (! Schema::hasTable('blog_articles')) {
            return collect();
        }

        return BlogArticle::query()
            ->with(['game', 'gameService'])
            ->published()
            ->where(function ($query) use ($service): void {
                $query
                    ->where('service_id', $service->id)
                    ->orWhere('game_id', $service->game_id);
            })
            ->orderByRaw(
                'case when service_id = ? then 0 when game_id = ? then 1 else 2 end',
                [$service->id, $service->game_id]
            )
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    protected function serviceCalculatorTab(array $service): ?array
    {
        $kind = (string) ($service['kind'] ?? '');
        $pane = self::SUPPORTED_SERVICE_PANES[$kind] ?? null;

        return $pane ? array_merge($pane, $service, ['active' => true]) : null;
    }

    protected function serviceHero(array $game, array $service, array $serviceCard, mixed $reviews): array
    {
        $gameShortName = (string) ($game['shortName'] ?? $game['name'] ?? 'Game');
        $serviceName = (string) ($service['name'] ?? 'Boosting Service');
        $reviewCount = max(1, collect($reviews)->count());

        return [
            'eyebrow' => "{$gameShortName} service",
            'title' => "{$gameShortName} {$serviceName}",
            'headline' => $this->serviceHeadline($game, $service),
            'subheadline' => $this->serviceSubheadline($game, $service),
            'startingPriceLabel' => $serviceCard['startingPriceLabel'] ?? 'Custom quote',
            'ratingLabel' => '5.0 / 5',
            'reviewLabel' => $reviewCount === 1 ? 'Verified review' : "{$reviewCount} verified reviews",
            'ctaUrl' => '#serviceCalculator',
        ];
    }

    protected function serviceCalculatorConfig(array $game, array $service, array $serviceCard, array $addons): array
    {
        $gameSlug = (string) ($game['slug'] ?? GameCatalog::DEFAULT_GAME_SLUG);
        $serviceSlug = (string) ($service['slug'] ?? '');
        $rankOptions = array_values((array) ($game['rankOptions'] ?? []));
        $targetRankOptions = array_values((array) ($game['rankOptionsWithRadiant'] ?? $rankOptions));
        $defaultCurrent = $this->defaultRank($rankOptions, data_get($game, 'defaults.currentRank'), 0);
        $defaultTarget = $this->defaultRank($targetRankOptions, data_get($game, 'defaults.desiredRank'), min(1, max(0, count($targetRankOptions) - 1)));

        if ($defaultTarget === $defaultCurrent && count($targetRankOptions) > 1) {
            $defaultTarget = $targetRankOptions[min(count($targetRankOptions) - 1, 1)];
        }

        return [
            'gameSlug' => $gameSlug,
            'gameName' => (string) ($game['name'] ?? 'Game'),
            'gameShortName' => (string) ($game['shortName'] ?? $game['name'] ?? 'Game'),
            'serviceSlug' => $serviceSlug,
            'serviceType' => (string) ($service['name'] ?? 'Boosting Service'),
            'serviceName' => (string) ($service['name'] ?? 'Boosting Service'),
            'calculatorKind' => $this->calculatorKind($service),
            'rankOptions' => $rankOptions,
            'targetRankOptions' => $targetRankOptions,
            'queueOptions' => [
                ['value' => 'normal', 'label' => 'Account Shared'],
                ['value' => 'self_play', 'label' => 'Duo Queue'],
            ],
            'defaults' => [
                'currentRank' => $defaultCurrent,
                'desiredRank' => $defaultTarget,
                'queueType' => 'normal',
                'currentRR' => 0,
                'avgRRPerWin' => '18',
                'region' => 'EU',
                'platform' => 'PC',
            ],
            'addons' => $addons,
            'startingPriceLabel' => $serviceCard['startingPriceLabel'] ?? 'Custom quote',
            'checkoutUrl' => route('checkout', [
                'game' => $gameSlug,
                'service' => $serviceSlug,
            ]),
            'pricingEndpoint' => route('pricing.calculate'),
            'csrfToken' => csrf_token(),
        ];
    }

    protected function servicePageAddons(array $game, array $service, GameService $serviceModel): array
    {
        $gameSlug = (string) ($game['slug'] ?? GameCatalog::DEFAULT_GAME_SLUG);
        $serviceAddons = $serviceModel->relationLoaded('addons')
            ? $serviceModel->addons
            : $serviceModel->addons()->get();

        return $serviceAddons
            ->filter(function (GameAddon $addon): bool {
                $pivotStatus = $addon->pivot?->status ?? GameService::STATUS_PUBLISHED;

                return $addon->status === GameAddon::STATUS_PUBLISHED && $pivotStatus === GameService::STATUS_PUBLISHED;
            })
            ->map(function (GameAddon $addon) use ($gameSlug): array {
                $slug = (string) $addon->slug;
                $label = (string) $addon->label;
                $identifiers = [
                    Str::slug($slug ?: $label),
                    Str::slug($label),
                ];
                $isDuoQueue = in_array('duo-queue', $identifiers, true);
                $isStreaming = count(array_intersect($identifiers, ['streaming', 'streamed-games'])) > 0;
                $isExpress = count(array_intersect($identifiers, ['express', 'express-order', 'express-delivery'])) > 0;
                $isWinStreak = in_array('win-streak-guarantee', $identifiers, true);

                $payloadLabel = match (true) {
                    $isStreaming => $gameSlug === GameCatalog::DEFAULT_GAME_SLUG ? 'Streaming' : 'Streamed Games',
                    $isExpress => $gameSlug === GameCatalog::DEFAULT_GAME_SLUG ? 'Express Order' : 'Express Delivery',
                    $isWinStreak => $gameSlug === GameCatalog::DEFAULT_GAME_SLUG ? 'Bonus Win' : 'Win Streak Guarantee',
                    default => $label,
                };
                $displayLabel = match (true) {
                    $isStreaming => 'Streamed Games',
                    $isExpress => 'Express Delivery',
                    default => $label,
                };
                $special = match (true) {
                    $isDuoQueue => ['controlsQueue' => true],
                    $isStreaming => ['flag' => 'streamGames'],
                    $isExpress => ['flag' => 'expressDelivery'],
                    default => [],
                };

                return array_merge([
                    'slug' => $slug,
                    'label' => $displayLabel,
                    'payloadLabel' => $payloadLabel,
                    'description' => $addon->description ?: 'Optional service customization for this order.',
                    'available' => true,
                ], $special);
            })
            ->values()
            ->all();
    }

    protected function serviceOrderProcessSteps(array $game, array $service): array
    {
        $gameShortName = (string) ($game['shortName'] ?? $game['name'] ?? 'your game');
        $serviceName = (string) ($service['name'] ?? 'service');

        return [
            [
                'title' => 'Configure the target',
                'body' => "Choose your current and desired {$gameShortName} target so the {$serviceName} scope is clear.",
            ],
            [
                'title' => 'Add delivery preferences',
                'body' => 'Pick queue type, streaming, express handling, and eligible add-ons before checkout.',
            ],
            [
                'title' => 'Confirm secure checkout',
                'body' => 'The server recalculates your final price and stores the validated order details.',
            ],
            [
                'title' => 'Track completion',
                'body' => 'Follow delivery updates, chat with support, and review progress from your order workspace.',
            ],
        ];
    }

    protected function estimatedDelivery(array $service): array
    {
        $kind = (string) ($service['kind'] ?? '');

        return [
            'label' => match ($kind) {
                'faceit_elo', 'predator_boost' => 'Custom review after checkout',
                'placement_matches' => 'Usually 3 to 12 hours',
                'rank_boost', 'radiant_boost' => 'Usually 1 to 3 days',
                default => 'Confirmed after order review',
            },
            'description' => 'Delivery depends on rank gap, queue conditions, region, selected add-ons, and booster availability. Express handling can shorten assignment and scheduling time.',
        ];
    }

    protected function serviceHeadline(array $game, array $service): string
    {
        $gameShortName = (string) ($game['shortName'] ?? $game['name'] ?? 'Game');
        $serviceName = (string) ($service['name'] ?? 'Boosting');

        return match ((string) ($service['kind'] ?? '')) {
            'faceit_elo' => "Push your {$gameShortName} Faceit ELO with a scoped, secure order.",
            'predator_boost' => "Start a focused {$gameShortName} Predator push with verified high-rank boosters.",
            'placement_matches' => "Lock in stronger {$gameShortName} placements without guessing the delivery path.",
            'rank_boost' => "Climb from your current rank to your target with tracked {$gameShortName} delivery.",
            default => "Order {$gameShortName} {$serviceName} with clear pricing and managed delivery.",
        };
    }

    protected function serviceSubheadline(array $game, array $service): string
    {
        $gameShortName = (string) ($game['shortName'] ?? $game['name'] ?? 'Game');
        $serviceName = (string) ($service['name'] ?? 'service');

        return "Configure your {$serviceName}, choose delivery add-ons, review the live server-backed quote, and continue to secure {$gameShortName} checkout when everything looks right.";
    }

    protected function calculatorKind(array $service): string
    {
        return match ((string) ($service['kind'] ?? '')) {
            'rank_boost', 'radiant_boost' => 'rank_to_rank',
            'placement_matches' => 'placement',
            default => 'fixed',
        };
    }

    protected function defaultRank(array $options, mixed $preferred, int $fallbackIndex = 0): ?string
    {
        if ($options === []) {
            return null;
        }

        if (is_string($preferred) && in_array($preferred, $options, true)) {
            return $preferred;
        }

        return $options[$fallbackIndex] ?? $options[0] ?? null;
    }

    protected function servicePayloadFromGame(array $game, string $serviceSlug): array
    {
        return collect($game['services'] ?? [])
            ->first(fn (array $service): bool => ($service['slug'] ?? null) === $serviceSlug) ?? [];
    }

    protected function gameCards(Collection $games, bool $singularRoutes = false): array
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
                        'url' => route('games.categories.show', ['category' => $game->category->slug]),
                    ] : null,
                    'url' => route('game.show', ['game' => $slug]),
                    'ctaUrl' => route('checkout', ['game' => $slug]),
                ];
            })
            ->values()
            ->all();
    }

    protected function serviceCards(Collection $services, bool $singularRoutes = false): array
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
                    'url' => route('game.services.show', [
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

    protected function publishedServicesForGame(Game $game): Collection
    {
        $services = $game->relationLoaded('services')
            ? $game->services
            : $game->services()->with(['game', 'pricingRules'])->orderBy('sort_order')->orderBy('id')->get();

        return $services
            ->where('status', Game::STATUS_PUBLISHED)
            ->sortBy(fn (GameService $service): string => sprintf('%08d-%08d', (int) $service->sort_order, (int) $service->id))
            ->values();
    }

    protected function relatedServiceCardsForGame(Game $game, Collection $gameServices, int $limit = 6): array
    {
        $gameSlug = (string) $game->slug;
        $serviceKinds = $gameServices
            ->pluck('kind')
            ->map(fn (mixed $kind): string => Str::slug((string) $kind, '_'))
            ->filter()
            ->unique()
            ->values();

        $candidates = $this->services->publishedCatalogServices()
            ->filter(function (GameService $service) use ($game, $gameSlug, $serviceKinds): bool {
                if (! $service->game instanceof Game || (int) $service->game_id === (int) $game->id) {
                    return false;
                }

                if ($gameSlug !== GameCatalog::DEFAULT_GAME_SLUG && (string) $service->game->slug === GameCatalog::DEFAULT_GAME_SLUG) {
                    return false;
                }

                return $serviceKinds->isEmpty()
                    || $serviceKinds->contains(Str::slug((string) $service->kind, '_'));
            })
            ->take($limit)
            ->values();

        if ($candidates->isEmpty()) {
            $candidates = $this->services->publishedCatalogServices()
                ->filter(fn (GameService $service): bool => $service->game instanceof Game && (int) $service->game_id !== (int) $game->id)
                ->take($limit)
                ->values();
        }

        return $this->serviceCards($candidates, singularRoutes: true);
    }

    protected function gamePageFaqs(mixed $faqs, array $game): Collection
    {
        $items = collect($faqs)->values();

        if ($items->isNotEmpty()) {
            return $items;
        }

        $gameShortName = (string) ($game['shortName'] ?? $game['name'] ?? 'this game');

        return collect([
            [
                'question' => "Is {$gameShortName} boosting safe?",
                'answer' => "Every {$gameShortName} order is scoped before checkout, assigned to vetted boosters, and supported with optional VPN protection where account-shared delivery applies.",
            ],
            [
                'question' => "How fast is {$gameShortName} delivery?",
                'answer' => 'Delivery depends on the service, target, region, queue conditions, and add-ons. Standard orders begin after checkout and assignment, with priority options available when speed matters.',
            ],
            [
                'question' => "Can I play while my {$gameShortName} order is active?",
                'answer' => 'You can keep playing when the selected service supports Duo or Self-Play. For account-shared orders, wait for support guidance before logging in during active delivery.',
            ],
            [
                'question' => "Do you use VPN for {$gameShortName} orders?",
                'answer' => 'VPN protection is available for supported account-shared services and is used to keep location handling consistent with the order requirements.',
            ],
        ]);
    }

    protected function gamePageReviews(mixed $reviews, array $game): Collection
    {
        $items = collect($reviews)->values();

        if ($items->isNotEmpty()) {
            return $items;
        }

        $gameShortName = (string) ($game['shortName'] ?? $game['name'] ?? 'Game');

        return collect([
            [
                'author_name' => 'Verified Customer',
                'service' => "{$gameShortName} Rank Boosting",
                'quote' => 'Clear checkout, quick assignment, and steady progress updates until the order was complete.',
            ],
            [
                'author_name' => 'Returning Customer',
                'service' => "{$gameShortName} Placement Matches",
                'quote' => 'Support answered quickly and the delivery details were easy to follow from start to finish.',
            ],
            [
                'author_name' => 'Competitive Player',
                'service' => "{$gameShortName} Coaching",
                'quote' => 'The service felt professional, secure, and focused on the exact goal I needed handled.',
            ],
        ]);
    }

    protected function gameOrderProcessSteps(array $game): array
    {
        $gameShortName = (string) ($game['shortName'] ?? $game['name'] ?? 'your game');

        return [
            [
                'title' => 'Choose service',
                'body' => "Pick the {$gameShortName} service that matches your rank, level, unlock, or coaching goal.",
            ],
            [
                'title' => 'Customize order',
                'body' => 'Set region, platform, delivery mode, target, and eligible add-ons before checkout.',
            ],
            [
                'title' => 'Secure checkout',
                'body' => 'Review the order summary, confirm details, and pay through approved payment providers.',
            ],
            [
                'title' => 'Booster starts',
                'body' => 'A vetted booster is assigned according to the game, service type, region, and delivery needs.',
            ],
            [
                'title' => 'Track progress/support',
                'body' => 'Follow updates, ask questions, and receive completion notes through support and order tracking.',
            ],
        ];
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

        $adminFeatured = $games
            ->filter(fn (Game $game): bool => (bool) data_get($game->metadata, 'featured', false))
            ->values();

        if ($adminFeatured->isNotEmpty()) {
            return $this->gameCards(
                $adminFeatured
                    ->merge($games->reject(fn (Game $game): bool => $adminFeatured->contains('id', $game->id)))
                    ->take(8)
                    ->values()
            );
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
        $featured = collect($this->serviceCards($this->services->homepageFeaturedServices(4)));

        return $featured
            ->merge($popular)
            ->unique(fn (array $service): string => (string) ($service['id'] ?? ($service['gameSlug'] ?? 'multi').':'.($service['slug'] ?? $service['name'] ?? 'service')))
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
            'rank_boost', 'radiant_boost', 'placement_matches', 'ranked_wins', 'unlock_services', 'camos_unlock_service', 'faceit_elo', 'predator_boost' => 9.00,
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
