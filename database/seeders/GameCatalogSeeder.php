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
    public function run(): void
    {
        if (! Schema::hasTable('games') || ! Schema::hasTable('game_categories')) {
            return;
        }

        $categories = $this->seedCategories();

        foreach ($this->games() as $index => $definition) {
            $categoryId = $categories[$definition['category']]?->id ?? null;
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
                    'metadata' => $definition['metadata'] ?? [],
                ],
            );

            $this->seedSeo($game, $definition['seo']);
            $services = $this->seedServices($game, $definition['services']);
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
        }

        return $categories;
    }

    protected function seedServices(Game $game, array $definitions): array
    {
        $services = [];

        foreach (array_values($definitions) as $index => $definition) {
            $pricingService = $game->slug === 'valorant'
                ? config("pricing.services.{$definition['name']}", [])
                : [];
            $kind = (string) ($definition['kind'] ?? $pricingService['kind'] ?? Str::slug($definition['name'], '_'));

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
                    'metadata' => $definition['metadata'] ?? [],
                ],
            );

            $services[$service->slug] = $service;
            $this->seedSeo($service, [
                'title' => "{$game->short_name} {$service->name} | GGWPBoost",
                'description' => $service->description ?: "Order {$game->short_name} {$service->name} with clear pricing, vetted boosters, and secure checkout.",
                'robots' => 'index,follow',
                'schema_type' => 'Service',
            ]);
        }

        return $services;
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
            ];
        }

        return [
            'calculator_key' => match ($service->kind) {
                'rank_boost', 'radiant_boost' => 'rank_to_rank',
                'placement_matches' => 'placement_quantity',
                'power_leveling' => 'level_range',
                'weapon_leveling' => 'weapon_level_range',
                'battle_pass_completion' => 'battle_pass_tiers',
                'challenges' => 'challenge_quantity',
                'farming' => 'farming_goal',
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
                default => 9.00,
            },
            'currency' => 'USD',
            'conditions' => [
                'future_calculator_ready' => true,
            ],
        ];
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
                'description' => 'Competitive VALORANT boosting, placement, coaching, unlock, and Radiant push services.',
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
                'services' => [
                    ['name' => 'Rank Boosting', 'kind' => 'rank_boost', 'description' => 'Move from your current rank to your target rank with a clear rank-to-rank quote.'],
                    ['name' => 'Placement Matches', 'kind' => 'placement_matches', 'description' => 'Complete placement games with pricing based on previous rank and match count.'],
                    ['name' => 'Radiant Boost', 'kind' => 'radiant_boost', 'description' => 'Premium high-rank push toward Radiant with careful scheduling and tracking.'],
                    ['name' => 'Ranked Wins', 'kind' => 'ranked_wins', 'description' => 'Order a specific number of ranked wins from your current division.'],
                    ['name' => 'Coaching', 'kind' => 'coaching', 'description' => 'Book focused VALORANT sessions for mechanics, agents, VOD review, and climb planning.'],
                    ['name' => 'Unlock Services', 'kind' => 'unlock_services', 'description' => 'Request account-safe unlock support for eligible VALORANT objectives.'],
                    ['name' => 'Battle Pass Completion', 'kind' => 'battle_pass_completion', 'description' => 'Progress seasonal battle pass tiers with managed delivery.'],
                ],
                'ranks' => config('pricing.rank_order', []),
                'addons' => ['offline-mode', 'specific-agents', 'one-trick-agent', 'solo-queue-only', 'no-5-stack', 'bonus-win', 'streaming', 'express-order', 'normalize-scores', 'record-clips'],
                'service_addons' => [],
            ],
            [
                'slug' => 'league-of-legends',
                'name' => 'League of Legends',
                'short_name' => 'League',
                'category' => 'moba',
                'description' => 'League of Legends rank boosting, placement matches, coaching, and duo queue services.',
                'seo' => [
                    'title' => 'League of Legends Boosting | GGWPBoost',
                    'description' => 'Compare League of Legends rank boosting, placements, coaching, and duo queue options with secure checkout.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet(['Rank Boosting', 'Placement Matches', 'Coaching']),
                'ranks' => ['Iron IV', 'Bronze IV', 'Silver IV', 'Gold IV', 'Platinum IV', 'Emerald IV', 'Diamond IV', 'Master', 'Grandmaster', 'Challenger'],
                'addons' => ['duo-queue', 'offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'coaching-addon', 'win-streak-guarantee', 'specific-booster'],
            ],
            [
                'slug' => 'cs2',
                'name' => 'CS2',
                'short_name' => 'CS2',
                'category' => 'fps',
                'description' => 'CS2 Premier, Faceit ELO, placement, coaching, and rank boosting services.',
                'seo' => [
                    'title' => 'CS2 Boosting and Faceit ELO | GGWPBoost',
                    'description' => 'Order CS2 rank boosting, Faceit ELO, placement matches, and coaching with extensible pricing rules.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet(['Rank Boosting', 'Placement Matches', 'Coaching', 'Faceit ELO']),
                'ranks' => ['Silver', 'Gold Nova', 'Master Guardian', 'Legendary Eagle', 'Supreme', 'Global Elite', 'Faceit Level 10'],
                'addons' => ['duo-queue', 'offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'coaching-addon', 'specific-booster'],
            ],
            [
                'slug' => 'apex-legends',
                'name' => 'Apex Legends',
                'short_name' => 'Apex',
                'category' => 'fps',
                'description' => 'Apex Legends rank boosting, Predator push, placements, coaching, and battle pass completion.',
                'seo' => [
                    'title' => 'Apex Legends Boosting and Predator Boost | GGWPBoost',
                    'description' => 'Order Apex Legends rank boosting, Predator boost, placement support, coaching, and battle pass completion.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet(['Rank Boosting', 'Placement Matches', 'Coaching', 'Battle Pass Completion', 'Challenges', 'Predator Boost']),
                'ranks' => ['Rookie', 'Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Master', 'Predator'],
                'addons' => ['duo-queue', 'offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'win-streak-guarantee', 'specific-booster'],
            ],
            [
                'slug' => 'overwatch-2',
                'name' => 'Overwatch 2',
                'short_name' => 'Overwatch 2',
                'category' => 'fps',
                'description' => 'Overwatch 2 role queue rank boosting, placements, and coaching services.',
                'seo' => [
                    'title' => 'Overwatch 2 Boosting | GGWPBoost',
                    'description' => 'Compare Overwatch 2 rank boosting, placement matches, coaching, and optional priority delivery.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet(['Rank Boosting', 'Placement Matches', 'Coaching', 'Challenges']),
                'ranks' => ['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Master', 'Grandmaster', 'Champion'],
                'addons' => ['duo-queue', 'offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'coaching-addon', 'specific-booster'],
            ],
            [
                'slug' => 'rainbow-6-siege-x',
                'name' => 'Rainbow 6 Siege X',
                'short_name' => 'R6 Siege X',
                'category' => 'fps',
                'description' => 'Rainbow 6 Siege X rank boosting, placements, coaching, challenges, and battle pass services.',
                'seo' => [
                    'title' => 'Rainbow 6 Siege X Boosting | GGWPBoost',
                    'description' => 'Order Rainbow 6 Siege X rank boosting, placements, coaching, battle pass, and challenge services.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet(['Rank Boosting', 'Placement Matches', 'Coaching', 'Battle Pass Completion', 'Challenges']),
                'ranks' => ['Copper', 'Bronze', 'Silver', 'Gold', 'Platinum', 'Emerald', 'Diamond', 'Champion'],
                'addons' => ['duo-queue', 'offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'specific-booster'],
            ],
            [
                'slug' => 'black-ops-6',
                'name' => 'Black Ops 6',
                'short_name' => 'BO6',
                'category' => 'fps',
                'description' => 'Black Ops 6 weapon leveling, unlock services, battle pass, challenges, and coaching.',
                'seo' => [
                    'title' => 'Black Ops 6 Boosting and Weapon Leveling | GGWPBoost',
                    'description' => 'Order Black Ops 6 weapon leveling, unlock services, battle pass, challenges, and coaching.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet(['Weapon Leveling', 'Unlock Services', 'Battle Pass Completion', 'Challenges', 'Coaching']),
                'ranks' => ['Level 1', 'Level 25', 'Level 55', 'Prestige 1', 'Prestige Master'],
                'addons' => ['offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'specific-booster'],
            ],
            [
                'slug' => 'rocket-league',
                'name' => 'Rocket League',
                'short_name' => 'Rocket League',
                'category' => 'fps',
                'description' => 'Rocket League rank boosting, placements, coaching, and seasonal challenge services.',
                'seo' => [
                    'title' => 'Rocket League Boosting | GGWPBoost',
                    'description' => 'Order Rocket League rank boosting, placements, coaching, and seasonal challenge completion.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet(['Rank Boosting', 'Placement Matches', 'Coaching', 'Challenges']),
                'ranks' => ['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Champion', 'Grand Champion', 'Supersonic Legend'],
                'addons' => ['duo-queue', 'offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'coaching-addon', 'specific-booster'],
            ],
            [
                'slug' => 'battlefield-6',
                'name' => 'Battlefield 6',
                'short_name' => 'Battlefield 6',
                'category' => 'fps',
                'description' => 'Battlefield 6 rank boosting, weapon leveling, unlock, battle pass, and challenge services.',
                'seo' => [
                    'title' => 'Battlefield 6 Boosting and Weapon Leveling | GGWPBoost',
                    'description' => 'Order Battlefield 6 rank boosting, weapon leveling, unlock services, battle pass, and challenges.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet(['Rank Boosting', 'Weapon Leveling', 'Unlock Services', 'Battle Pass Completion', 'Challenges', 'Coaching']),
                'ranks' => ['Recruit', 'Specialist', 'Veteran', 'Elite', 'Master'],
                'addons' => ['offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'specific-booster'],
            ],
            [
                'slug' => 'marvel-rivals',
                'name' => 'Marvel Rivals',
                'short_name' => 'Marvel Rivals',
                'category' => 'fps',
                'description' => 'Marvel Rivals rank boosting, placements, coaching, battle pass, and challenge services.',
                'seo' => [
                    'title' => 'Marvel Rivals Boosting | GGWPBoost',
                    'description' => 'Order Marvel Rivals rank boosting, placement matches, coaching, battle pass, and challenges.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet(['Rank Boosting', 'Placement Matches', 'Coaching', 'Battle Pass Completion', 'Challenges']),
                'ranks' => ['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Grandmaster', 'Eternity', 'One Above All'],
                'addons' => ['duo-queue', 'offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'coaching-addon', 'specific-booster'],
            ],
            [
                'slug' => 'fragpunk',
                'name' => 'FragPunk',
                'short_name' => 'FragPunk',
                'category' => 'fps',
                'description' => 'FragPunk rank boosting, placements, coaching, battle pass, and challenges.',
                'seo' => [
                    'title' => 'FragPunk Boosting | GGWPBoost',
                    'description' => 'Order FragPunk rank boosting, placements, coaching, battle pass, and challenge services.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet(['Rank Boosting', 'Placement Matches', 'Coaching', 'Battle Pass Completion', 'Challenges']),
                'ranks' => ['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Master'],
                'addons' => ['duo-queue', 'offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'coaching-addon', 'specific-booster'],
            ],
            [
                'slug' => 'deadlock',
                'name' => 'Deadlock',
                'short_name' => 'Deadlock',
                'category' => 'fps',
                'description' => 'Deadlock rank boosting, placements, coaching, and challenge services.',
                'seo' => [
                    'title' => 'Deadlock Boosting | GGWPBoost',
                    'description' => 'Order Deadlock rank boosting, placements, coaching, and challenge support.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet(['Rank Boosting', 'Placement Matches', 'Coaching', 'Challenges']),
                'ranks' => ['Initiate', 'Seeker', 'Alchemist', 'Arcanist', 'Oracle', 'Phantom'],
                'addons' => ['duo-queue', 'offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'coaching-addon', 'specific-booster'],
            ],
            [
                'slug' => 'tft',
                'name' => 'TFT',
                'short_name' => 'TFT',
                'category' => 'moba',
                'description' => 'TFT rank boosting, placement, coaching, and challenge services.',
                'seo' => [
                    'title' => 'TFT Boosting | GGWPBoost',
                    'description' => 'Order TFT rank boosting, placements, coaching, and challenge services.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet(['Rank Boosting', 'Placement Matches', 'Coaching', 'Challenges']),
                'ranks' => ['Iron', 'Bronze', 'Silver', 'Gold', 'Platinum', 'Emerald', 'Diamond', 'Master', 'Grandmaster', 'Challenger'],
                'addons' => ['offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'coaching-addon', 'specific-booster'],
            ],
            [
                'slug' => 'wild-rift',
                'name' => 'Wild Rift',
                'short_name' => 'Wild Rift',
                'category' => 'moba',
                'description' => 'Wild Rift rank boosting, placements, coaching, and duo queue services.',
                'seo' => [
                    'title' => 'Wild Rift Boosting | GGWPBoost',
                    'description' => 'Order Wild Rift rank boosting, placements, coaching, and duo queue services.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet(['Rank Boosting', 'Placement Matches', 'Coaching']),
                'ranks' => ['Iron', 'Bronze', 'Silver', 'Gold', 'Platinum', 'Emerald', 'Diamond', 'Master', 'Grandmaster', 'Challenger'],
                'addons' => ['duo-queue', 'offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'coaching-addon', 'specific-booster'],
            ],
            [
                'slug' => 'heroes-of-the-storm',
                'name' => 'Heroes of the Storm',
                'short_name' => 'HotS',
                'category' => 'moba',
                'description' => 'Heroes of the Storm rank boosting, placements, and coaching services.',
                'seo' => [
                    'title' => 'Heroes of the Storm Boosting | GGWPBoost',
                    'description' => 'Order Heroes of the Storm rank boosting, placements, and coaching.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet(['Rank Boosting', 'Placement Matches', 'Coaching']),
                'ranks' => ['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Master', 'Grand Master'],
                'addons' => ['duo-queue', 'offline-mode', 'vpn-protection', 'streamed-games', 'priority-order', 'express-delivery', 'coaching-addon', 'specific-booster'],
            ],
            [
                'slug' => 'diablo-4',
                'name' => 'Diablo 4',
                'short_name' => 'Diablo 4',
                'category' => 'mmo-rpg',
                'description' => 'Diablo 4 power leveling, unlock services, farming, challenges, coaching, and seasonal progression support.',
                'seo' => [
                    'title' => 'Diablo 4 Power Leveling | GGWPBoost',
                    'description' => 'Order Diablo 4 power leveling, unlock services, farming, challenges, coaching, and seasonal objective support.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet(['Power Leveling', 'Unlock Services', 'Farming', 'Challenges', 'Coaching']),
                'ranks' => ['Level 1', 'Level 50', 'Level 60', 'Paragon 100', 'Paragon 200'],
                'addons' => ['offline-mode', 'vpn-protection', 'priority-order', 'express-delivery', 'coaching-addon', 'specific-booster'],
            ],
            [
                'slug' => 'new-world',
                'name' => 'New World',
                'short_name' => 'New World',
                'category' => 'mmo-rpg',
                'description' => 'New World power leveling, unlock services, farming, and coaching.',
                'seo' => [
                    'title' => 'New World Power Leveling | GGWPBoost',
                    'description' => 'Order New World power leveling, unlock services, farming, and coaching.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet(['Power Leveling', 'Unlock Services', 'Farming', 'Coaching']),
                'ranks' => ['Level 1', 'Level 25', 'Level 50', 'Level 65', 'Endgame Ready'],
                'addons' => ['offline-mode', 'vpn-protection', 'priority-order', 'express-delivery', 'coaching-addon', 'specific-booster'],
            ],
            [
                'slug' => 'arc-raiders',
                'name' => 'Arc Raiders',
                'short_name' => 'Arc Raiders',
                'category' => 'mmo-rpg',
                'description' => 'Arc Raiders power leveling, unlock services, challenges, and farming.',
                'seo' => [
                    'title' => 'Arc Raiders Boosting and Farming | GGWPBoost',
                    'description' => 'Order Arc Raiders power leveling, unlock services, challenges, and farming support.',
                    'schema_type' => 'Service',
                ],
                'services' => $this->serviceSet(['Power Leveling', 'Unlock Services', 'Challenges', 'Farming']),
                'ranks' => ['Starter', 'Scavenger', 'Veteran', 'Elite', 'Endgame Ready'],
                'addons' => ['offline-mode', 'vpn-protection', 'priority-order', 'express-delivery', 'specific-booster'],
            ],
        ];
    }

    protected function serviceSet(array $names): array
    {
        return collect($names)
            ->map(fn (string $name): array => [
                'name' => $name,
                'kind' => $this->serviceKind($name),
                'description' => $this->serviceDescription($name),
            ])
            ->all();
    }

    protected function serviceKind(string $service): string
    {
        return match ($service) {
            'Rank Boosting' => 'rank_boost',
            'Placement Matches' => 'placement_matches',
            'Placements' => 'placement_matches',
            'Coaching' => 'coaching',
            'Unlock Services' => 'unlock_services',
            'Power Leveling' => 'power_leveling',
            'Battle Pass Completion' => 'battle_pass_completion',
            'Battle Pass' => 'battle_pass_completion',
            'Weapon Leveling' => 'weapon_leveling',
            'Challenges' => 'challenges',
            'Farming' => 'farming',
            'Faceit ELO' => 'faceit_elo',
            'Predator Boost' => 'predator_boost',
            'Radiant Boost' => 'radiant_boost',
            default => Str::slug($service, '_'),
        };
    }

    protected function serviceDescription(string $service): string
    {
        return match ($service) {
            'Rank Boosting' => 'Rank-to-rank progression with a clear target and tracked delivery.',
            'Placement Matches' => 'Placement match completion with configurable match counts.',
            'Placements' => 'Placement match completion with configurable match counts.',
            'Coaching' => 'Personal coaching sessions for mechanics, strategy, and review.',
            'Unlock Services' => 'Objective and account unlock support for eligible content.',
            'Power Leveling' => 'Level-range progression prepared for future level calculators.',
            'Battle Pass Completion' => 'Seasonal tier progression with extensible quantity pricing.',
            'Battle Pass' => 'Seasonal tier progression with extensible quantity pricing.',
            'Weapon Leveling' => 'Weapon XP and level progression with future weapon-range support.',
            'Challenges' => 'Challenge and objective completion with tracked delivery.',
            'Farming' => 'Repeatable resource, currency, or progression farming with scoped goals.',
            'Faceit ELO' => 'Faceit ELO progression for CS2 competitive goals.',
            'Predator Boost' => 'High-rank Apex progression toward Predator leaderboard goals.',
            'Radiant Boost' => 'High-rank VALORANT progression toward Radiant.',
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
