<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Game;
use App\Models\GameAddon;
use App\Models\GameCategory;
use App\Models\GameRank;
use App\Models\GameService;
use App\Models\Review;
use App\Models\SeoMetadata;
use App\Models\ServicePricingRule;
use App\Support\BoostingCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GameCatalogSeeder extends Seeder
{
    protected const FEATURED_GAME_SLUGS = [
        'valorant',
        'league-of-legends',
        'cs2',
        'apex-legends',
        'overwatch-2',
        'black-ops-6',
        'rocket-league',
    ];

    public function run(): void
    {
        if (! Schema::hasTable('games') || ! Schema::hasTable('game_categories')) {
            return;
        }

        $categories = $this->seedCategories();

        foreach ($this->games() as $index => $definition) {
            $categoryId = $categories[$definition['category']]?->id ?? null;
            $metadata = $this->gameMetadata($definition);
            $game = Game::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'game_category_id' => $categoryId,
                    'name' => $definition['name'],
                    'short_name' => $definition['short_name'],
                    'description' => $definition['description'],
                    'status' => Game::STATUS_PUBLISHED,
                    'sort_order' => $index + 1,
                    'assets' => $definition['assets'] ?? [],
                    'metadata' => $metadata,
                ],
            );

            $this->seedSeo($game, $definition['seo']);
            $services = $this->seedServices($game, $definition['services']);
            $this->archiveObsoleteSeedServices($game, array_keys($services));
            $this->seedRanks($game, $definition['ranks'] ?? []);
            $addons = $this->seedAddons($game, $definition['addons']);
            $this->seedServiceAddonLinks($services, $addons, $definition['service_addons'] ?? []);
            $this->seedPricingRules($game, $services, $addons);
            $this->seedGameFaqs($game, $services);
            $this->seedGameReviews($game, $services);
        }

        $this->backfillLegacyContent();
    }

    protected function seedCategories(): array
    {
        $categories = [];

        foreach ($this->categories() as $index => $definition) {
            $categories[$definition['slug']] = GameCategory::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'status' => GameCategory::STATUS_PUBLISHED,
                    'sort_order' => $index + 1,
                    'metadata' => $definition['metadata'] ?? [],
                ],
            );

            $this->seedSeo($categories[$definition['slug']], [
                'title' => $definition['seo']['title'] ?? "{$definition['name']} Boosting Games | GGWPBoost",
                'description' => $definition['seo']['description'] ?? "Compare {$definition['name']} boosting services across active games with secure checkout and live support.",
                'robots' => 'index,follow',
                'schema_type' => 'CollectionPage',
                'priority' => 0.7,
            ]);
        }

        return $categories;
    }

    protected function seedServices(Game $game, array $definitions): array
    {
        $services = [];

        foreach (array_values($definitions) as $index => $definition) {
            $pricingService = $game->slug === 'valorant'
                ? config("pricing.services.".($definition['pricing_service'] ?? $definition['name']), [])
                : [];
            $kind = (string) ($definition['kind'] ?? $pricingService['kind'] ?? Str::slug($definition['name'], '_'));
            $metadata = array_replace_recursive([
                'aliases' => array_values($definition['aliases'] ?? []),
            ], $definition['metadata'] ?? []);

            $service = GameService::query()->updateOrCreate(
                [
                    'game_id' => $game->id,
                    'slug' => $definition['slug'] ?? Str::slug($definition['name']),
                ],
                [
                    'name' => $definition['name'],
                    'kind' => $kind,
                    'description' => $definition['description'] ?? null,
                    'status' => Game::STATUS_PUBLISHED,
                    'sort_order' => $index + 1,
                    'config' => array_replace($pricingService, $definition['config'] ?? []),
                    'metadata' => $metadata,
                ],
            );

            $services[$service->slug] = $service;
            $this->seedSeo($service, array_replace(
                $this->serviceSeo($game, $service),
                $definition['seo'] ?? []
            ));
        }

        return $services;
    }

    protected function archiveObsoleteSeedServices(Game $game, array $activeSlugs): void
    {
        $previousSeedSlugs = [
            'rank-boosting',
            'placement-matches',
            'coaching',
            'unlock-services',
            'battle-pass-completion',
            'challenges',
            'farming',
            'camos-unlock-service',
            'weapon-leveling',
        ];
        $obsoleteSlugs = array_values(array_diff($previousSeedSlugs, $activeSlugs));

        if ($obsoleteSlugs === []) {
            return;
        }

        GameService::query()
            ->where('game_id', $game->id)
            ->whereIn('slug', $obsoleteSlugs)
            ->update(['status' => GameService::STATUS_ARCHIVED]);
    }

    protected function seedRanks(Game $game, array $ranks): void
    {
        foreach (array_values($ranks) as $index => $rank) {
            $label = is_array($rank) ? (string) $rank['label'] : (string) $rank;

            GameRank::query()->updateOrCreate(
                [
                    'game_id' => $game->id,
                    'slug' => Str::slug($label),
                ],
                [
                    'label' => $label,
                    'division' => is_array($rank) ? ($rank['division'] ?? $this->division($label)) : $this->division($label),
                    'sort_order' => $index + 1,
                    'icon_url' => $game->slug === 'valorant' ? BoostingCatalog::rankIconUrl($label) : null,
                    'icon_path' => null,
                    'metadata' => is_array($rank) ? ($rank['metadata'] ?? []) : [],
                ],
            );
        }
    }

    protected function seedAddons(Game $game, array $addonSlugs): array
    {
        $addons = [];
        $pricingRules = config('pricing.addons', []);
        $definitions = $this->addonDefinitions();

        foreach (array_values($addonSlugs) as $index => $addonSlug) {
            $definition = $definitions[$addonSlug] ?? [
                'label' => Str::headline($addonSlug),
                'description' => 'Optional service customization for this game.',
                'pricing' => ['type' => 'free', 'value' => 0],
                'aliases' => [],
            ];
            $label = (string) $definition['label'];
            $pricingRule = $game->slug === 'valorant' && isset($pricingRules[$label])
                ? $this->normalizeAddonPricingRule($pricingRules[$label])
                : $this->normalizeAddonPricingRule($definition['pricing'] ?? ['type' => 'free', 'value' => 0]);

            $addon = GameAddon::query()->updateOrCreate(
                [
                    'game_id' => $game->id,
                    'slug' => $addonSlug,
                ],
                [
                    'label' => $label,
                    'description' => $definition['description'] ?? null,
                    'icon' => $definition['icon'] ?? null,
                    'status' => Game::STATUS_PUBLISHED,
                    'sort_order' => $index + 1,
                    'pricing_type' => $pricingRule['type'],
                    'pricing_value' => $pricingRule['value'],
                    'pricing_rule' => $pricingRule,
                    'availability_rule' => [
                        'services' => [],
                    ],
                    'metadata' => [
                        'aliases' => array_values($definition['aliases'] ?? []),
                    ],
                ],
            );

            $addons[$addon->slug] = $addon;
        }

        return $addons;
    }

    protected function seedServiceAddonLinks(array $services, array $addons, array $serviceAddonMap): void
    {
        foreach ($services as $serviceSlug => $service) {
            $addonSlugs = $serviceAddonMap[$serviceSlug] ?? array_keys($addons);

            foreach (array_values($addonSlugs) as $index => $addonSlug) {
                $addon = $addons[$addonSlug] ?? null;

                if (! $addon instanceof GameAddon) {
                    continue;
                }

                $service->addons()->syncWithoutDetaching([
                    $addon->id => [
                        'status' => Game::STATUS_PUBLISHED,
                        'sort_order' => $index + 1,
                        'availability_rule' => json_encode([
                            'service_slug' => $service->slug,
                            'addon_slug' => $addon->slug,
                        ], JSON_THROW_ON_ERROR),
                        'metadata' => json_encode([], JSON_THROW_ON_ERROR),
                    ],
                ]);
            }
        }
    }

    protected function seedPricingRules(Game $game, array $services, array $addons): void
    {
        foreach ($services as $service) {
            $baseRule = $this->basePricingRuleFor($game, $service);

            ServicePricingRule::query()->updateOrCreate(
                [
                    'game_id' => $game->id,
                    'slug' => "{$service->slug}-base",
                ],
                [
                    'service_id' => $service->id,
                    'addon_id' => null,
                    'name' => "{$service->name} base pricing",
                    'scope' => ServicePricingRule::SCOPE_BASE,
                    'calculator_key' => $baseRule['calculator_key'],
                    'pricing_type' => $baseRule['pricing_type'],
                    'amount' => $baseRule['amount'],
                    'currency' => 'USD',
                    'min_quantity' => $baseRule['min_quantity'] ?? null,
                    'max_quantity' => $baseRule['max_quantity'] ?? null,
                    'status' => ServicePricingRule::STATUS_PUBLISHED,
                    'sort_order' => 1,
                    'conditions' => $baseRule['conditions'] ?? [],
                    'tiers' => $baseRule['tiers'] ?? [],
                    'metadata' => $baseRule['metadata'] ?? [],
                ],
            );
        }

        foreach ($addons as $addon) {
            $pricingRule = $addon->pricing_rule ?? ['type' => 'free', 'value' => 0];

            ServicePricingRule::query()->updateOrCreate(
                [
                    'game_id' => $game->id,
                    'slug' => "{$addon->slug}-addon",
                ],
                [
                    'service_id' => null,
                    'addon_id' => $addon->id,
                    'name' => "{$addon->label} addon pricing",
                    'scope' => ServicePricingRule::SCOPE_ADDON,
                    'calculator_key' => 'addon_modifier',
                    'pricing_type' => $pricingRule['type'] ?? 'free',
                    'amount' => $pricingRule['value'] ?? 0,
                    'currency' => 'USD',
                    'status' => ServicePricingRule::STATUS_PUBLISHED,
                    'sort_order' => 100 + (int) $addon->sort_order,
                    'conditions' => [
                        'addon_slug' => $addon->slug,
                    ],
                    'tiers' => [],
                    'metadata' => [
                        'legacy_pricing_rule' => $pricingRule,
                    ],
                ],
            );
        }
    }

    protected function seedGameFaqs(Game $game, array $services): void
    {
        $rankBoosting = $services['rank-boosting'] ?? reset($services);

        foreach ([
            [
                'question' => "How does {$game->short_name} boosting work?",
                'answer' => "Choose a {$game->short_name} service, review the quote, and complete checkout. Your order workspace keeps chat, progress, and delivery updates together.",
                'order' => 1000 + ((int) $game->sort_order * 10) + 1,
            ],
            [
                'question' => "Can I add extras to my {$game->short_name} order?",
                'answer' => "Yes. Available addons are attached to each service so options such as express delivery, duo queue, streamed games, or VPN protection can be enabled where they apply.",
                'order' => 1000 + ((int) $game->sort_order * 10) + 2,
            ],
        ] as $entry) {
            Faq::query()->updateOrCreate(
                ['order' => $entry['order']],
                [
                    'game_id' => $game->id,
                    'service_id' => $rankBoosting instanceof GameService ? $rankBoosting->id : null,
                    'question' => $entry['question'],
                    'answer' => $entry['answer'],
                    'order' => $entry['order'],
                ],
            );
        }
    }

    protected function seedGameReviews(Game $game, array $services): void
    {
        $service = $services['rank-boosting'] ?? reset($services);

        Review::query()->updateOrCreate(
            ['sort_order' => 1000 + (int) $game->sort_order],
            [
                'game_id' => $game->id,
                'service_id' => $service instanceof GameService ? $service->id : null,
                'author_name' => "{$game->short_name} Customer",
                'service' => $service instanceof GameService ? $service->name : 'Rank Boosting',
                'quote' => "Clear pricing, responsive support, and a smooth {$game->short_name} order from setup to delivery.",
                'sort_order' => 1000 + (int) $game->sort_order,
            ],
        );
    }

    protected function seedSeo(Model $model, array $seo): void
    {
        if (! Schema::hasTable('seo_metadata')) {
            return;
        }

        SeoMetadata::query()->updateOrCreate(
            [
                'seoable_type' => $model->getMorphClass(),
                'seoable_id' => $model->getKey(),
                'context' => $seo['context'] ?? 'default',
            ],
            [
                'meta_title' => $seo['title'] ?? null,
                'meta_description' => $seo['description'] ?? null,
                'canonical_url' => $seo['canonical'] ?? null,
                'robots' => $seo['robots'] ?? 'index,follow',
                'schema_type' => $seo['schema_type'] ?? 'WebPage',
                'open_graph_image' => $seo['image'] ?? null,
                'include_in_sitemap' => (bool) ($seo['include_in_sitemap'] ?? true),
                'changefreq' => $seo['changefreq'] ?? 'weekly',
                'priority' => $seo['priority'] ?? 0.8,
                'metadata' => $seo['metadata'] ?? [],
            ],
        );
    }

    protected function serviceSeo(Game $game, GameService $service): array
    {
        $gameShortName = (string) ($game->short_name ?: $game->name);

        if ($game->slug === 'modern-warfare-3' && $service->kind === 'camos') {
            return [
                'title' => 'MW3 Camos Unlock Service | GGWPBoost',
                'description' => 'Order an MW3 camos unlock service with scoped camo goals, secure checkout, and live order support.',
                'robots' => 'index,follow',
                'schema_type' => 'Service',
            ];
        }

        $seo = match ((string) $service->kind) {
            'radiant_boost' => [
                'title' => "Buy Radiant Boost for {$gameShortName} | GGWPBoost",
                'description' => "Buy a {$gameShortName} Radiant boost with vetted high-rank boosters, secure checkout, and live support.",
            ],
            'predator_boost' => [
                'title' => 'Apex Predator Boost Service | GGWPBoost',
                'description' => 'Order an Apex Predator boost service with high-rank boosters, clear scope, secure checkout, and support.',
            ],
            'faceit_elo' => [
                'title' => 'CS2 Faceit ELO Boost | GGWPBoost',
                'description' => 'Order a CS2 Faceit ELO boost with clear pricing, vetted boosters, secure checkout, and live support.',
            ],
            'camos_unlock_service' => [
                'title' => 'MW3 Camos Unlock Service | GGWPBoost',
                'description' => 'Order an MW3 camos unlock service with scoped camo goals, secure checkout, and live order support.',
            ],
            default => [
                'title' => "{$gameShortName} {$service->name} | GGWPBoost",
                'description' => "Order {$gameShortName} {$service->name} with clear pricing, vetted boosters, secure checkout, and live support.",
            ],
        };

        return array_merge($seo, [
            'robots' => 'index,follow',
            'schema_type' => 'Service',
        ]);
    }

    protected function backfillLegacyContent(): void
    {
        $valorant = Game::query()->where('slug', 'valorant')->first();

        if (! $valorant instanceof Game) {
            return;
        }

        $rankBoosting = GameService::query()
            ->where('game_id', $valorant->id)
            ->where('slug', 'rank-boosting')
            ->first();

        foreach ([
            'orders',
            'pending_checkouts',
            'faqs',
            'testimonials',
            'blog_articles',
            'promo_codes',
            'promo_code_addons',
            'addon_settings',
        ] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'game_id')) {
                DB::table($tableName)->whereNull('game_id')->update(['game_id' => $valorant->id]);
            }
        }

        foreach ([
            'orders',
            'pending_checkouts',
            'faqs',
            'testimonials',
            'blog_articles',
            'promo_codes',
        ] as $tableName) {
            if ($rankBoosting instanceof GameService && Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'service_id')) {
                DB::table($tableName)
                    ->whereNull('service_id')
                    ->where('game_id', $valorant->id)
                    ->update(['service_id' => $rankBoosting->id]);
            }
        }
    }

    protected function normalizeAddonPricingRule(array $rule): array
    {
        $type = Str::lower((string) ($rule['type'] ?? 'free'));
        $value = is_numeric($rule['value'] ?? null) ? (float) $rule['value'] : 0.0;

        return match ($type) {
            'fixed', 'flat' => [
                'type' => ServicePricingRule::PRICING_FIXED,
                'value' => $value,
            ],
            'percent', 'percentage' => [
                'type' => ServicePricingRule::PRICING_PERCENTAGE,
                'value' => $type === 'percent' && $value <= 1 ? $value * 100 : $value,
            ],
            'multiplier' => [
                'type' => ServicePricingRule::PRICING_MULTIPLIER,
                'value' => $value,
            ],
            'bonus_win' => [
                'type' => ServicePricingRule::PRICING_DYNAMIC,
                'value' => 0,
                'calculator_key' => 'bonus_win',
            ],
            default => [
                'type' => 'free',
                'value' => 0,
            ],
        };
    }

    protected function basePricingRuleFor(Game $game, GameService $service): array
    {
        if ($game->slug === 'valorant' && in_array($service->kind, ['rank_boost', 'radiant_boost'], true)) {
            return [
                'calculator_key' => 'valorant_rank_to_rank',
                'pricing_type' => ServicePricingRule::PRICING_DYNAMIC,
                'amount' => null,
                'conditions' => [
                    'rank_order_source' => 'pricing.rank_order',
                    'base_prices_source' => 'pricing.base_prices',
                ],
                'metadata' => [
                    'pricing_status' => 'configured',
                    'pricing_source' => 'valorant_dynamic_config',
                ],
            ];
        }

        if ($game->slug === 'valorant' && $service->kind === 'placement_matches') {
            return [
                'calculator_key' => 'valorant_quantity_by_rank',
                'pricing_type' => ServicePricingRule::PRICING_DYNAMIC,
                'amount' => null,
                'min_quantity' => 1,
                'max_quantity' => 5,
                'conditions' => [
                    'base_prices_source' => 'pricing.base_prices.Placement Matches',
                ],
                'metadata' => [
                    'pricing_status' => 'configured',
                    'pricing_source' => 'valorant_dynamic_config',
                ],
            ];
        }

        if ($game->slug === 'valorant' && $service->kind === 'ranked_wins') {
            return [
                'calculator_key' => 'valorant_quantity_by_rank',
                'pricing_type' => ServicePricingRule::PRICING_DYNAMIC,
                'amount' => null,
                'min_quantity' => 1,
                'max_quantity' => 5,
                'conditions' => [
                    'base_prices_source' => 'pricing.base_prices.Ranked Wins',
                ],
                'metadata' => [
                    'pricing_status' => 'configured',
                    'pricing_source' => 'valorant_dynamic_config',
                ],
            ];
        }

        return [
            'calculator_key' => match ($service->kind) {
                'rank_boost', 'ranked_boosting', 'division_boosting', 'divisions', 'premier_boosting', 'radiant_boost' => 'rank_to_rank',
                'placement_matches' => 'placement_quantity',
                'power_leveling' => 'level_range',
                'leveling' => 'level_range',
                'weapon_leveling' => 'weapon_level_range',
                'vehicle_leveling' => 'vehicle_level_range',
                'battle_pass_completion' => 'battle_pass_tiers',
                'challenges' => 'challenge_quantity',
                'wins', 'ranked_wins', 'quick_match_wins' => 'win_quantity',
                'kills_boost' => 'kill_quantity',
                'coin_farming', 'blueprint_farming', 'farming' => 'farming_goal',
                default => 'flat_service',
            },
            'pricing_type' => ServicePricingRule::PRICING_FIXED,
            'amount' => match ($service->kind) {
                'coaching' => 19.00,
                'weapon_leveling' => 15.00,
                'power_leveling' => 24.00,
                'battle_pass_completion' => 29.00,
                'challenges' => 12.00,
                'farming' => 18.00,
                'coin_farming', 'blueprint_farming' => 18.00,
                'boss_kills', 'raids' => 20.00,
                'paragon', 'glyph_leveling' => 16.00,
                'top_500', 'radiant_boost', 'predator_boost' => 39.00,
                default => 9.00,
            },
            'currency' => 'USD',
            'conditions' => [
                'future_calculator_ready' => true,
            ],
            'metadata' => [
                'pricing_status' => 'placeholder',
                'placeholder_reason' => 'MVP seed price until live service pricing is configured.',
            ],
        ];
    }

    protected function gameMetadata(array $definition): array
    {
        $metadata = $definition['metadata'] ?? [];
        $featuredIndex = array_search($definition['slug'], self::FEATURED_GAME_SLUGS, true);

        if ($featuredIndex === false) {
            unset($metadata['featured'], $metadata['featured_sort_order']);

            return $metadata;
        }

        return array_replace($metadata, [
            'featured' => true,
            'featured_sort_order' => $featuredIndex + 1,
        ]);
    }

    protected function categories(): array
    {
        return [
            [
                'slug' => 'fps',
                'name' => 'FPS',
                'description' => 'Competitive shooters with ranked ladders, weapon progression, battle passes, and seasonal goals.',
            ],
            [
                'slug' => 'moba',
                'name' => 'MOBA',
                'description' => 'Lane-based competitive games with divisions, placements, and role specialization.',
            ],
            [
                'slug' => 'mmo-rpg',
                'name' => 'MMO / RPG',
                'description' => 'Progression games with leveling, unlocks, farming, seasonal objectives, and account goals.',
            ],
        ];
    }

    protected function games(): array
    {
        return [
            [
                'slug' => 'valorant',
                'name' => 'Valorant',
                'short_name' => 'VALORANT',
                'category' => 'fps',
                'description' => 'VALORANT rank boost, ranked wins, placements, and Radiant boost services.',
                'metadata' => [
                    'default_current_rank' => config('boosting.default_current_rank', 'Gold III'),
                    'default_desired_rank' => config('boosting.default_desired_rank', 'Platinum III'),
                    'rank_icon_source' => 'valorant-api',
                ],
                'seo' => [
                    'title' => 'VALORANT Boosting Services | GGWPBoost',
                    'description' => 'Order VALORANT rank boosting, placement matches, Radiant boosts, coaching, and unlock services with secure checkout.',
                    'schema_type' => 'Service',
                    'priority' => 1.0,
                ],
                'services' => $this->serviceSet([
                    ['name' => 'Rank Boost', 'slug' => 'rank-boosting', 'kind' => 'rank_boost', 'pricing_service' => 'Rank Boosting', 'aliases' => ['Rank Boosting']],
                    ['name' => 'Ranked Wins', 'slug' => 'ranked-wins', 'kind' => 'ranked_wins'],
                    ['name' => 'Placements', 'slug' => 'placement-matches', 'kind' => 'placement_matches', 'pricing_service' => 'Placement Matches', 'aliases' => ['Placement Matches', 'Placement Games']],
                    ['name' => 'Radiant Boost', 'slug' => 'radiant-boost', 'kind' => 'radiant_boost'],
                ]),
                'ranks' => config('pricing.rank_order', []),
                'addons' => ['offline-mode', 'specific-agents', 'one-trick-agent', 'solo-queue-only', 'no-5-stack', 'bonus-win', 'streaming', 'express-order', 'normalize-scores', 'record-clips'],
                'service_addons' => [],
            ],
            [
                'slug' => 'league-of-legends',
                'name' => 'League of Legends',
                'short_name' => 'League',
                'category' => 'moba',
                'description' => 'League of Legends division boosting, placements, coaching, Arena, and challenges.',
                'seo' => [
                    'title' => 'League of Legends Boosting | GGWPBoost',
                    'description' => 'Compare League division boosting, placements, coaching, Arena, and challenge services.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet([
                    ['name' => 'Division Boosting', 'slug' => 'division-boosting', 'kind' => 'division_boosting', 'aliases' => ['Rank Boosting', 'Rank Boost']],
                    ['name' => 'Placements', 'slug' => 'placement-matches', 'kind' => 'placement_matches', 'aliases' => ['Placement Matches']],
                    'Coaching',
                    'Arena',
                    'Challenges',
                ]),
                'ranks' => ['Iron IV', 'Bronze IV', 'Silver IV', 'Gold IV', 'Platinum IV', 'Emerald IV', 'Diamond IV', 'Master', 'Grandmaster', 'Challenger'],
                'addons' => ['duo-queue', 'offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'coaching-addon', 'win-streak-guarantee', 'specific-booster'],
            ],
            [
                'slug' => 'cs2',
                'name' => 'CS2',
                'short_name' => 'CS2',
                'category' => 'fps',
                'description' => 'CS2 Premier boosting, Faceit ELO, placements, Competitive, and Wingman services.',
                'seo' => [
                    'title' => 'CS2 Boosting and Faceit ELO | GGWPBoost',
                    'description' => 'Order CS2 Premier boosting, Faceit ELO, placements, Competitive, and Wingman services.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet([
                    ['name' => 'Premier Boosting', 'slug' => 'premier-boosting', 'kind' => 'premier_boosting', 'aliases' => ['Rank Boosting', 'Rank Boost']],
                    ['name' => 'Faceit ELO', 'slug' => 'faceit-elo', 'kind' => 'faceit_elo'],
                    ['name' => 'Placements', 'slug' => 'placement-matches', 'kind' => 'placement_matches', 'aliases' => ['Placement Matches']],
                    'Competitive',
                    'Wingman',
                ]),
                'ranks' => ['Silver', 'Gold Nova', 'Master Guardian', 'Legendary Eagle', 'Supreme', 'Global Elite', 'Faceit Level 10'],
                'addons' => ['duo-queue', 'offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'coaching-addon', 'specific-booster'],
            ],
            [
                'slug' => 'apex-legends',
                'name' => 'Apex Legends',
                'short_name' => 'Apex',
                'category' => 'fps',
                'description' => 'Apex Legends rank boost, Predator boost, badge boosting, kills boost, and challenges.',
                'seo' => [
                    'title' => 'Apex Legends Boosting and Predator Boost | GGWPBoost',
                    'description' => 'Order Apex rank boost, Predator boost, badge boosting, kills boost, and challenges.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet([
                    ['name' => 'Rank Boost', 'slug' => 'rank-boosting', 'kind' => 'rank_boost', 'aliases' => ['Rank Boosting']],
                    ['name' => 'Predator Boost', 'slug' => 'predator-boost', 'kind' => 'predator_boost'],
                    'Badge Boosting',
                    'Kills Boost',
                    'Challenges',
                ]),
                'ranks' => ['Rookie', 'Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Master', 'Predator'],
                'addons' => ['duo-queue', 'offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'win-streak-guarantee', 'specific-booster'],
            ],
            [
                'slug' => 'overwatch-2',
                'name' => 'Overwatch 2',
                'short_name' => 'Overwatch 2',
                'category' => 'fps',
                'description' => 'Overwatch 2 rank boosting, Top 500, coaching, and hero progression services.',
                'seo' => [
                    'title' => 'Overwatch 2 Boosting | GGWPBoost',
                    'description' => 'Compare Overwatch 2 rank boosting, Top 500, coaching, and hero progression services.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet(['Rank Boosting', 'Top 500', 'Coaching', 'Hero Progression']),
                'ranks' => ['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Master', 'Grandmaster', 'Champion'],
                'addons' => ['duo-queue', 'offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'coaching-addon', 'specific-booster'],
            ],
            [
                'slug' => 'rainbow-6-siege-x',
                'name' => 'Rainbow 6 Siege X',
                'short_name' => 'R6 Siege X',
                'category' => 'fps',
                'description' => 'Rainbow 6 Siege X rank boost, battle pass, and cup boosting services.',
                'seo' => [
                    'title' => 'Rainbow 6 Siege X Boosting | GGWPBoost',
                    'description' => 'Order Rainbow 6 Siege X rank boost, battle pass, and cup boosting services.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet([
                    ['name' => 'Rank Boost', 'slug' => 'rank-boosting', 'kind' => 'rank_boost', 'aliases' => ['Rank Boosting']],
                    ['name' => 'Battle Pass', 'slug' => 'battle-pass', 'kind' => 'battle_pass_completion', 'aliases' => ['Battle Pass Completion']],
                    'Cup Boosting',
                ]),
                'ranks' => ['Copper', 'Bronze', 'Silver', 'Gold', 'Platinum', 'Emerald', 'Diamond', 'Champion'],
                'addons' => ['duo-queue', 'offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'specific-booster'],
            ],
            [
                'slug' => 'black-ops-6',
                'name' => 'Black Ops 6',
                'short_name' => 'BO6',
                'category' => 'fps',
                'description' => 'Black Ops 6 unlock all, camos, prestige, weapon leveling, ranked boosting, Dark Ops, and calling cards.',
                'seo' => [
                    'title' => 'Black Ops 6 Boosting and Weapon Leveling | GGWPBoost',
                    'description' => 'Order BO6 unlock all, camos, prestige, weapon leveling, ranked boosting, and Dark Ops.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet(['Unlock All', 'Camos', 'Prestige', 'Weapon Leveling', 'Ranked Boosting', 'Dark Ops', 'Calling Cards']),
                'ranks' => ['Level 1', 'Level 25', 'Level 55', 'Prestige 1', 'Prestige Master'],
                'addons' => ['offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'specific-booster'],
            ],
            [
                'slug' => 'modern-warfare-3',
                'name' => 'Modern Warfare 3',
                'short_name' => 'MW3',
                'category' => 'fps',
                'description' => 'MW3 camos, unlock services, KD boosting, and operator unlocks.',
                'seo' => [
                    'title' => 'MW3 Boosting and Camos Unlocks | GGWPBoost',
                    'description' => 'Order MW3 camos, unlock services, KD boosting, and operator unlocks.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet([
                    ['name' => 'Camos', 'slug' => 'camos', 'kind' => 'camos', 'aliases' => ['Camos Unlock Service', 'Camo Unlocks']],
                    'Unlock Services',
                    'KD Boosting',
                    'Operator Unlocks',
                ]),
                'ranks' => ['Level 1', 'Level 25', 'Level 55', 'Prestige 1', 'Interstellar Camos'],
                'addons' => ['offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'specific-booster'],
            ],
            [
                'slug' => 'rocket-league',
                'name' => 'Rocket League',
                'short_name' => 'Rocket League',
                'category' => 'fps',
                'description' => 'Rocket League rank boost, placements, and tournament boosting services.',
                'seo' => [
                    'title' => 'Rocket League Boosting | GGWPBoost',
                    'description' => 'Order Rocket League rank boost, placements, and tournament boosting services.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet([
                    ['name' => 'Rank Boost', 'slug' => 'rank-boosting', 'kind' => 'rank_boost', 'aliases' => ['Rank Boosting']],
                    ['name' => 'Placements', 'slug' => 'placement-matches', 'kind' => 'placement_matches', 'aliases' => ['Placement Matches']],
                    'Tournament Boosting',
                ]),
                'ranks' => ['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Champion', 'Grand Champion', 'Supersonic Legend'],
                'addons' => ['duo-queue', 'offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'coaching-addon', 'specific-booster'],
            ],
            [
                'slug' => 'battlefield-6',
                'name' => 'Battlefield 6',
                'short_name' => 'Battlefield 6',
                'category' => 'fps',
                'description' => 'Battlefield 6 unlock all, weapon leveling, vehicle leveling, KD boost, battle pass, and challenges.',
                'seo' => [
                    'title' => 'Battlefield 6 Boosting and Weapon Leveling | GGWPBoost',
                    'description' => 'Order Battlefield 6 unlock all, weapon leveling, vehicle leveling, KD boost, battle pass, and challenges.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet([
                    'Unlock All',
                    'Weapon Leveling',
                    'Vehicle Leveling',
                    'KD Boost',
                    ['name' => 'Battle Pass', 'slug' => 'battle-pass', 'kind' => 'battle_pass_completion', 'aliases' => ['Battle Pass Completion']],
                    'Challenges',
                ]),
                'ranks' => ['Recruit', 'Specialist', 'Veteran', 'Elite', 'Master'],
                'addons' => ['offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'specific-booster'],
            ],
            [
                'slug' => 'marvel-rivals',
                'name' => 'Marvel Rivals',
                'short_name' => 'Marvel Rivals',
                'category' => 'fps',
                'description' => 'Marvel Rivals rank boosting, hero proficiency, skin unlocks, and missions.',
                'seo' => [
                    'title' => 'Marvel Rivals Boosting | GGWPBoost',
                    'description' => 'Order Marvel Rivals rank boosting, hero proficiency, skin unlocks, and missions.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet(['Rank Boosting', 'Hero Proficiency', 'Skin Unlocks', 'Missions']),
                'ranks' => ['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Grandmaster', 'Eternity', 'One Above All'],
                'addons' => ['duo-queue', 'offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'coaching-addon', 'specific-booster'],
            ],
            [
                'slug' => 'fragpunk',
                'name' => 'FragPunk',
                'short_name' => 'FragPunk',
                'category' => 'fps',
                'description' => 'FragPunk rank boost, wins, placements, and weapon leveling services.',
                'seo' => [
                    'title' => 'FragPunk Boosting | GGWPBoost',
                    'description' => 'Order FragPunk rank boost, wins, placements, and weapon leveling services.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet([
                    ['name' => 'Rank Boost', 'slug' => 'rank-boosting', 'kind' => 'rank_boost', 'aliases' => ['Rank Boosting']],
                    'Wins',
                    ['name' => 'Placements', 'slug' => 'placement-matches', 'kind' => 'placement_matches', 'aliases' => ['Placement Matches']],
                    'Weapon Leveling',
                ]),
                'ranks' => ['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Master'],
                'addons' => ['duo-queue', 'offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'coaching-addon', 'specific-booster'],
            ],
            [
                'slug' => 'deadlock',
                'name' => 'Deadlock',
                'short_name' => 'Deadlock',
                'category' => 'fps',
                'description' => 'Deadlock rank boosting, coaching, and battle pass services.',
                'seo' => [
                    'title' => 'Deadlock Boosting | GGWPBoost',
                    'description' => 'Order Deadlock rank boosting, coaching, and battle pass services.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet([
                    'Rank Boosting',
                    'Coaching',
                    ['name' => 'Battle Pass', 'slug' => 'battle-pass', 'kind' => 'battle_pass_completion', 'aliases' => ['Battle Pass Completion']],
                ]),
                'ranks' => ['Initiate', 'Seeker', 'Alchemist', 'Arcanist', 'Oracle', 'Phantom'],
                'addons' => ['duo-queue', 'offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'coaching-addon', 'specific-booster'],
            ],
            [
                'slug' => 'tft',
                'name' => 'TFT',
                'short_name' => 'TFT',
                'category' => 'moba',
                'description' => 'TFT divisions, placements, Hyper Roll, and Double Up services.',
                'seo' => [
                    'title' => 'TFT Boosting | GGWPBoost',
                    'description' => 'Order TFT divisions, placements, Hyper Roll, and Double Up services.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet([
                    'Divisions',
                    ['name' => 'Placements', 'slug' => 'placement-matches', 'kind' => 'placement_matches', 'aliases' => ['Placement Matches']],
                    'Hyper Roll',
                    'Double Up',
                ]),
                'ranks' => ['Iron', 'Bronze', 'Silver', 'Gold', 'Platinum', 'Emerald', 'Diamond', 'Master', 'Grandmaster', 'Challenger'],
                'addons' => ['offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'coaching-addon', 'specific-booster'],
            ],
            [
                'slug' => 'wild-rift',
                'name' => 'Wild Rift',
                'short_name' => 'Wild Rift',
                'category' => 'moba',
                'description' => 'Wild Rift divisions, wins, and Legendary Queue services.',
                'seo' => [
                    'title' => 'Wild Rift Boosting | GGWPBoost',
                    'description' => 'Order Wild Rift divisions, wins, and Legendary Queue services.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet(['Divisions', 'Wins', 'Legendary Queue']),
                'ranks' => ['Iron', 'Bronze', 'Silver', 'Gold', 'Platinum', 'Emerald', 'Diamond', 'Master', 'Grandmaster', 'Challenger'],
                'addons' => ['duo-queue', 'offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'coaching-addon', 'specific-booster'],
            ],
            [
                'slug' => 'heroes-of-the-storm',
                'name' => 'Heroes of the Storm',
                'short_name' => 'HotS',
                'category' => 'moba',
                'description' => 'Heroes of the Storm rank boosting, placements, and Quick Match wins.',
                'seo' => [
                    'title' => 'Heroes of the Storm Boosting | GGWPBoost',
                    'description' => 'Order Heroes of the Storm rank boosting, placements, and Quick Match wins.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet([
                    'Rank Boosting',
                    ['name' => 'Placements', 'slug' => 'placement-matches', 'kind' => 'placement_matches', 'aliases' => ['Placement Matches']],
                    'Quick Match Wins',
                ]),
                'ranks' => ['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Master', 'Grand Master'],
                'addons' => ['duo-queue', 'offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'coaching-addon', 'specific-booster'],
            ],
            [
                'slug' => 'diablo-4',
                'name' => 'Diablo 4',
                'short_name' => 'Diablo 4',
                'category' => 'mmo-rpg',
                'description' => 'Diablo 4 power leveling, Paragon, boss kills, glyph leveling, and build services.',
                'seo' => [
                    'title' => 'Diablo 4 Power Leveling | GGWPBoost',
                    'description' => 'Order Diablo 4 power leveling, Paragon, boss kills, glyph leveling, and build services.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet(['Power Leveling', 'Paragon', 'Boss Kills', 'Glyph Leveling', 'Build Services']),
                'ranks' => ['Level 1', 'Level 50', 'Level 60', 'Paragon 100', 'Paragon 200'],
                'addons' => ['offline-mode', 'vpn-protection', 'priority-order', 'express-delivery', 'coaching-addon', 'specific-booster'],
            ],
            [
                'slug' => 'new-world',
                'name' => 'New World',
                'short_name' => 'New World',
                'category' => 'mmo-rpg',
                'description' => 'New World leveling and weapon mastery services.',
                'seo' => [
                    'title' => 'New World Power Leveling | GGWPBoost',
                    'description' => 'Order New World leveling and weapon mastery services.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet(['Leveling', 'Weapon Mastery']),
                'ranks' => ['Level 1', 'Level 25', 'Level 50', 'Level 65', 'Endgame Ready'],
                'addons' => ['offline-mode', 'vpn-protection', 'priority-order', 'express-delivery', 'coaching-addon', 'specific-booster'],
            ],
            [
                'slug' => 'arc-raiders',
                'name' => 'Arc Raiders',
                'short_name' => 'Arc Raiders',
                'category' => 'mmo-rpg',
                'description' => 'Arc Raiders power leveling, coin farming, raids, blueprint farming, quests, and expeditions.',
                'seo' => [
                    'title' => 'Arc Raiders Boosting and Farming | GGWPBoost',
                    'description' => 'Order Arc Raiders power leveling, coin farming, raids, blueprint farming, quests, and expeditions.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet(['Power Leveling', 'Coin Farming', 'Raids', 'Blueprint Farming', 'Quests', 'Expeditions']),
                'ranks' => ['Starter', 'Scavenger', 'Veteran', 'Elite', 'Endgame Ready'],
                'addons' => ['offline-mode', 'vpn-protection', 'priority-order', 'express-delivery', 'specific-booster'],
            ],
        ];
    }

    protected function serviceSet(array $definitions): array
    {
        return collect($definitions)
            ->map(function (array|string $definition): array {
                $definition = is_string($definition) ? ['name' => $definition] : $definition;
                $name = (string) $definition['name'];
                $aliases = array_values(array_unique([
                    ...$this->serviceAliases($name),
                    ...($definition['aliases'] ?? []),
                ]));
                $service = [
                    'name' => $name,
                    'slug' => $definition['slug'] ?? $this->serviceSlug($name),
                    'kind' => $definition['kind'] ?? $this->serviceKind($name),
                    'description' => $definition['description'] ?? $this->serviceDescription($name),
                    'metadata' => array_replace_recursive([
                        'aliases' => $aliases,
                    ], $definition['metadata'] ?? []),
                ];

                foreach (['config', 'pricing_service', 'seo'] as $optionalKey) {
                    if (array_key_exists($optionalKey, $definition)) {
                        $service[$optionalKey] = $definition[$optionalKey];
                    }
                }

                return $service;
            })
            ->all();
    }

    protected function serviceSlug(string $service): string
    {
        return match ($service) {
            'Rank Boost', 'Rank Boosting' => 'rank-boosting',
            'Placements', 'Placement Matches' => 'placement-matches',
            'Battle Pass', 'Battle Pass Completion' => 'battle-pass',
            default => Str::slug($service),
        };
    }

    protected function serviceKind(string $service): string
    {
        return match ($service) {
            'Rank Boost', 'Rank Boosting' => 'rank_boost',
            'Division Boosting' => 'division_boosting',
            'Ranked Boosting' => 'ranked_boosting',
            'Premier Boosting' => 'premier_boosting',
            'Placements', 'Placement Matches' => 'placement_matches',
            'Coaching' => 'coaching',
            'Unlock Services' => 'unlock_services',
            'Unlock All' => 'unlock_all',
            'Power Leveling' => 'power_leveling',
            'Leveling' => 'leveling',
            'Battle Pass', 'Battle Pass Completion' => 'battle_pass_completion',
            'Weapon Leveling' => 'weapon_leveling',
            'Vehicle Leveling' => 'vehicle_leveling',
            'Weapon Mastery' => 'weapon_mastery',
            'Camos' => 'camos',
            'Camos Unlock Service' => 'camos_unlock_service',
            'KD Boost' => 'kd_boost',
            'KD Boosting' => 'kd_boosting',
            'Challenges' => 'challenges',
            'Farming' => 'farming',
            'Coin Farming' => 'coin_farming',
            'Blueprint Farming' => 'blueprint_farming',
            'Faceit ELO' => 'faceit_elo',
            'Predator Boost' => 'predator_boost',
            'Radiant Boost' => 'radiant_boost',
            'Ranked Wins' => 'ranked_wins',
            'Wins' => 'wins',
            'Quick Match Wins' => 'quick_match_wins',
            'Badge Boosting' => 'badge_boosting',
            'Kills Boost' => 'kills_boost',
            'Raids' => 'raids',
            'Quests' => 'quests',
            'Expeditions' => 'expeditions',
            'Competitive' => 'competitive',
            'Wingman' => 'wingman',
            'Paragon' => 'paragon',
            'Boss Kills' => 'boss_kills',
            'Glyph Leveling' => 'glyph_leveling',
            'Build Services' => 'build_services',
            'Hero Proficiency' => 'hero_proficiency',
            'Skin Unlocks' => 'skin_unlocks',
            'Missions' => 'missions',
            'Operator Unlocks' => 'operator_unlocks',
            'Top 500' => 'top_500',
            'Hero Progression' => 'hero_progression',
            'Cup Boosting' => 'cup_boosting',
            'Tournament Boosting' => 'tournament_boosting',
            'Divisions' => 'divisions',
            'Hyper Roll' => 'hyper_roll',
            'Double Up' => 'double_up',
            'Legendary Queue' => 'legendary_queue',
            'Arena' => 'arena',
            'Prestige' => 'prestige',
            'Dark Ops' => 'dark_ops',
            'Calling Cards' => 'calling_cards',
            default => Str::slug($service, '_'),
        };
    }

    protected function serviceAliases(string $service): array
    {
        return match ($service) {
            'Rank Boost' => ['Rank Boosting'],
            'Placements' => ['Placement Matches', 'Placement Games'],
            'Battle Pass' => ['Battle Pass Completion'],
            'Camos' => ['Camos Unlock Service'],
            'KD Boosting' => ['KD Boost'],
            'Division Boosting', 'Divisions', 'Premier Boosting', 'Ranked Boosting' => ['Rank Boosting', 'Rank Boost'],
            default => [],
        };
    }

    protected function serviceDescription(string $service): string
    {
        return match ($service) {
            'Rank Boost', 'Rank Boosting', 'Division Boosting', 'Divisions', 'Premier Boosting', 'Ranked Boosting' => 'Rank progression with a clear target and tracked delivery.',
            'Placements', 'Placement Matches' => 'Placement match completion with configurable match counts.',
            'Coaching' => 'Personal coaching sessions for mechanics, strategy, and review.',
            'Unlock Services', 'Unlock All' => 'Objective and account unlock support for eligible content.',
            'Power Leveling', 'Leveling' => 'Level-range progression prepared for future level calculators.',
            'Battle Pass', 'Battle Pass Completion' => 'Seasonal tier progression with extensible quantity pricing.',
            'Weapon Leveling' => 'Weapon XP and level progression with future weapon-range support.',
            'Vehicle Leveling' => 'Vehicle progression with scoped goals and tracked delivery.',
            'Weapon Mastery' => 'Weapon mastery progression with scoped goals and tracked delivery.',
            'Camos', 'Camos Unlock Service' => 'Camo unlock goals with scoped delivery and managed order updates.',
            'Challenges', 'Dark Ops', 'Calling Cards', 'Missions', 'Quests', 'Expeditions' => 'Objective completion with tracked delivery.',
            'Farming', 'Coin Farming', 'Blueprint Farming' => 'Repeatable resource, currency, or progression farming with scoped goals.',
            'Faceit ELO' => 'Faceit ELO progression for CS2 competitive goals.',
            'Predator Boost' => 'High-rank Apex progression toward Predator leaderboard goals.',
            'Radiant Boost' => 'High-rank VALORANT progression toward Radiant.',
            'Ranked Wins', 'Wins', 'Quick Match Wins' => 'Win-count delivery with clear quantity and progress tracking.',
            'Badge Boosting' => 'Apex badge goal support with scoped delivery requirements.',
            'Kills Boost' => 'Kill-count goal support with tracked delivery.',
            'Raids', 'Boss Kills' => 'Encounter completion with scoped order goals.',
            'Paragon', 'Glyph Leveling' => 'Endgame progression with clear milestone tracking.',
            'Build Services' => 'Character setup and build support for endgame goals.',
            'Hero Proficiency', 'Hero Progression' => 'Hero progression support with tracked milestones.',
            'Skin Unlocks', 'Operator Unlocks' => 'Cosmetic unlock support for eligible account goals.',
            'Top 500' => 'High-rank push service for elite leaderboard goals.',
            'Cup Boosting', 'Tournament Boosting' => 'Competitive event support with scoped delivery.',
            'Hyper Roll', 'Double Up', 'Wingman', 'Competitive', 'Arena', 'Legendary Queue' => "{$service} delivery with tracked order progress.",
            'KD Boost', 'KD Boosting' => 'KD goal support with scoped delivery and managed order updates.',
            'Prestige' => 'Prestige progression with clear milestones and tracked delivery.',
            default => "{$service} delivery with extensible pricing rules.",
        };
    }

    protected function addonDefinitions(): array
    {
        $configured = collect(config('boosting.addons', []))
            ->mapWithKeys(fn (array $addon, string $slug): array => [
                $slug => [
                    'label' => $addon['label'],
                    'description' => $addon['description'],
                    'icon' => $addon['icon'] ?? null,
                    'pricing' => ['type' => 'free', 'value' => 0],
                    'aliases' => array_values($addon['aliases'] ?? []),
                ],
            ])
            ->all();

        return array_replace($configured, [
            'duo-queue' => [
                'label' => 'Duo Queue',
                'description' => 'Play alongside the booster where the game and service allow self-play delivery.',
                'pricing' => ['type' => 'multiplier', 'value' => 1.35],
                'aliases' => ['Duo', 'Self Play', 'Self-Play'],
            ],
            'vpn-protection' => [
                'label' => 'VPN Protection',
                'description' => 'Use location-aware protection for account-shared orders where appropriate.',
                'pricing' => ['type' => 'fixed', 'value' => 4.99],
                'aliases' => ['VPN', 'VPN protect'],
            ],
            'streamed-games' => [
                'label' => 'Streamed Games',
                'description' => 'Request streamed delivery or shareable session visibility where supported.',
                'pricing' => ['type' => 'percentage', 'value' => 10],
                'aliases' => ['Streaming', 'Live Streaming'],
            ],
            'priority-order' => [
                'label' => 'Priority Order',
                'description' => 'Place the order higher in the queue for faster assignment.',
                'pricing' => ['type' => 'percentage', 'value' => 15],
                'aliases' => ['Priority'],
            ],
            'express-delivery' => [
                'label' => 'Express Delivery',
                'description' => 'Shorten the delivery window with expedited scheduling.',
                'pricing' => ['type' => 'percentage', 'value' => 25],
                'aliases' => ['Express'],
            ],
            'coaching-addon' => [
                'label' => 'Coaching Addon',
                'description' => 'Add a short coaching review to the order after delivery.',
                'pricing' => ['type' => 'fixed', 'value' => 9.99],
                'aliases' => ['Coaching add-on'],
            ],
            'win-streak-guarantee' => [
                'label' => 'Win Streak Guarantee',
                'description' => 'Prioritize delivery strategy around a stronger win streak target.',
                'pricing' => ['type' => 'percentage', 'value' => 20],
                'aliases' => ['Win streak'],
            ],
            'specific-booster' => [
                'label' => 'Specific Booster',
                'description' => 'Request a preferred booster when availability allows.',
                'pricing' => ['type' => 'fixed', 'value' => 7.99],
                'aliases' => ['Preferred booster'],
            ],
        ]);
    }

    protected function division(string $rank): ?string
    {
        $parts = preg_split('/\s+/', trim($rank)) ?: [];

        return count($parts) > 1 ? end($parts) ?: null : null;
    }
}
