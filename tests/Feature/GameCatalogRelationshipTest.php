<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\Game;
use App\Models\GameAddon;
use App\Models\GameCategory;
use App\Models\GameService;
use App\Models\Review;
use App\Models\SeoMetadata;
use App\Models\ServicePricingRule;
use Database\Seeders\GameCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameCatalogRelationshipTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_mvp_seed_contains_games_services_addons_pricing_rules_and_seo(): void
    {
        $this->seed(GameCatalogSeeder::class);
        $serviceMap = $this->mvpServiceMap();

        $this->assertEqualsCanonicalizing(
            array_keys($serviceMap),
            Game::query()->pluck('slug')->sort()->values()->all()
        );

        foreach (['fps', 'mmo-rpg', 'moba'] as $categorySlug) {
            $this->assertDatabaseHas('game_categories', ['slug' => $categorySlug]);
        }

        foreach ($serviceMap as $gameSlug => $expectedServices) {
            $game = Game::query()->where('slug', $gameSlug)->firstOrFail();
            $actualServices = $game->services()
                ->where('status', GameService::STATUS_PUBLISHED)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (GameService $service): array => [
                    'name' => $service->name,
                    'slug' => $service->slug,
                ])
                ->all();

            $this->assertSame($expectedServices, $actualServices, "Unexpected service map for {$gameSlug}.");
        }

        foreach ([
            'Duo Queue',
            'Offline Mode',
            'VPN Protection',
            'Streamed Games',
            'Priority Order',
            'Express Delivery',
            'Coaching Addon',
            'Win Streak Guarantee',
            'Specific Booster',
        ] as $addonLabel) {
            $this->assertDatabaseHas('game_addons', ['label' => $addonLabel]);
        }

        $this->assertDatabaseHas('service_pricing_rules', ['pricing_type' => ServicePricingRule::PRICING_FIXED]);
        $this->assertDatabaseHas('service_pricing_rules', ['pricing_type' => ServicePricingRule::PRICING_PERCENTAGE]);
        $this->assertDatabaseHas('service_pricing_rules', ['pricing_type' => ServicePricingRule::PRICING_MULTIPLIER]);
        $this->assertDatabaseHas('service_pricing_rules', [
            'scope' => ServicePricingRule::SCOPE_BASE,
            'pricing_type' => ServicePricingRule::PRICING_DYNAMIC,
        ]);
        $this->assertGreaterThanOrEqual(19, SeoMetadata::query()->where('seoable_type', Game::class)->count());

        GameService::query()
            ->whereHas('game', fn ($query) => $query->where('slug', '!=', 'valorant'))
            ->with('pricingRules')
            ->where('status', GameService::STATUS_PUBLISHED)
            ->get()
            ->each(function (GameService $service): void {
                $baseRule = $service->pricingRules->firstWhere('scope', ServicePricingRule::SCOPE_BASE);

                $this->assertSame(ServicePricingRule::PRICING_FIXED, $baseRule?->pricing_type, "Expected fixed MVP pricing for {$service->game?->slug}/{$service->slug}.");
                $this->assertSame('placeholder', data_get($baseRule?->metadata, 'pricing_status'));
            });
    }

    public function test_game_catalog_seeder_is_idempotent(): void
    {
        $this->seed(GameCatalogSeeder::class);

        $counts = [
            'games' => Game::query()->count(),
            'services' => GameService::query()->count(),
            'seo' => SeoMetadata::query()->count(),
            'pricing_rules' => ServicePricingRule::query()->count(),
        ];

        $this->seed(GameCatalogSeeder::class);

        $this->assertSame($counts['games'], Game::query()->count());
        $this->assertSame($counts['services'], GameService::query()->count());
        $this->assertSame($counts['seo'], SeoMetadata::query()->count());
        $this->assertSame($counts['pricing_rules'], ServicePricingRule::query()->count());
    }

    public function test_seeded_mvp_game_and_service_urls_render(): void
    {
        $this->seed(GameCatalogSeeder::class);

        foreach ($this->mvpServiceMap() as $gameSlug => $services) {
            $this->get(route('game.show', ['game' => $gameSlug]))->assertOk();

            foreach ($services as $service) {
                $this->get(route('game.services.show', [
                    'game' => $gameSlug,
                    'service' => $service['slug'],
                ]))->assertOk();
            }
        }
    }

    public function test_core_catalog_relationships_resolve_through_eloquent(): void
    {
        $category = GameCategory::factory()->create();
        $game = Game::factory()->for($category, 'category')->create();
        $service = GameService::factory()->for($game, 'game')->create([
            'slug' => 'rank-boosting',
            'name' => 'Rank Boosting',
            'kind' => 'rank_boost',
        ]);
        $addon = GameAddon::factory()->for($game, 'game')->create([
            'slug' => 'express-delivery',
            'label' => 'Express Delivery',
            'pricing_type' => ServicePricingRule::PRICING_PERCENTAGE,
            'pricing_value' => 25,
        ]);

        $service->addons()->attach($addon->id, [
            'status' => Game::STATUS_PUBLISHED,
            'sort_order' => 1,
            'availability_rule' => json_encode(['service_slug' => $service->slug], JSON_THROW_ON_ERROR),
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
        ]);

        $rule = ServicePricingRule::factory()->for($game, 'game')->for($service, 'service')->for($addon, 'addon')->create([
            'slug' => 'express-delivery-addon',
            'scope' => ServicePricingRule::SCOPE_ADDON,
            'pricing_type' => ServicePricingRule::PRICING_PERCENTAGE,
            'amount' => 25,
        ]);
        $seo = $game->seoMetadata()->create([
            'context' => 'default',
            'meta_title' => 'Custom Game Boosting',
            'meta_description' => 'Custom game SEO metadata.',
            'robots' => 'index,follow',
            'schema_type' => 'Service',
            'include_in_sitemap' => true,
            'metadata' => [],
        ]);
        $faq = Faq::query()->create([
            'game_id' => $game->id,
            'service_id' => $service->id,
            'question' => 'How does this boost work?',
            'answer' => 'Through a catalog-backed service relationship.',
            'order' => 5000,
        ]);
        $review = Review::query()->create([
            'game_id' => $game->id,
            'service_id' => $service->id,
            'author_name' => 'Test Customer',
            'service' => 'Rank Boosting',
            'quote' => 'The relationship graph resolves cleanly.',
            'sort_order' => 5000,
        ]);

        $this->assertTrue($game->category->is($category));
        $this->assertTrue($game->services->contains($service));
        $this->assertTrue($service->game->is($game));
        $this->assertTrue($service->addons->contains($addon));
        $this->assertTrue($addon->services->contains($service));
        $this->assertTrue($service->pricingRules->contains($rule));
        $this->assertTrue($addon->pricingRules->contains($rule));
        $this->assertTrue($game->seoMetadata->is($seo));
        $this->assertTrue($faq->game->is($game));
        $this->assertTrue($faq->gameService->is($service));
        $this->assertTrue($review->game->is($game));
        $this->assertTrue($review->gameService->is($service));
    }

    protected function mvpServiceMap(): array
    {
        return [
            'apex-legends' => [
                ['name' => 'Rank Boost', 'slug' => 'rank-boosting'],
                ['name' => 'Predator Boost', 'slug' => 'predator-boost'],
                ['name' => 'Badge Boosting', 'slug' => 'badge-boosting'],
                ['name' => 'Kills Boost', 'slug' => 'kills-boost'],
                ['name' => 'Challenges', 'slug' => 'challenges'],
            ],
            'arc-raiders' => [
                ['name' => 'Power Leveling', 'slug' => 'power-leveling'],
                ['name' => 'Coin Farming', 'slug' => 'coin-farming'],
                ['name' => 'Raids', 'slug' => 'raids'],
                ['name' => 'Blueprint Farming', 'slug' => 'blueprint-farming'],
                ['name' => 'Quests', 'slug' => 'quests'],
                ['name' => 'Expeditions', 'slug' => 'expeditions'],
            ],
            'battlefield-6' => [
                ['name' => 'Unlock All', 'slug' => 'unlock-all'],
                ['name' => 'Weapon Leveling', 'slug' => 'weapon-leveling'],
                ['name' => 'Vehicle Leveling', 'slug' => 'vehicle-leveling'],
                ['name' => 'KD Boost', 'slug' => 'kd-boost'],
                ['name' => 'Battle Pass', 'slug' => 'battle-pass'],
                ['name' => 'Challenges', 'slug' => 'challenges'],
            ],
            'black-ops-6' => [
                ['name' => 'Unlock All', 'slug' => 'unlock-all'],
                ['name' => 'Camos', 'slug' => 'camos'],
                ['name' => 'Prestige', 'slug' => 'prestige'],
                ['name' => 'Weapon Leveling', 'slug' => 'weapon-leveling'],
                ['name' => 'Ranked Boosting', 'slug' => 'ranked-boosting'],
                ['name' => 'Dark Ops', 'slug' => 'dark-ops'],
                ['name' => 'Calling Cards', 'slug' => 'calling-cards'],
            ],
            'cs2' => [
                ['name' => 'Premier Boosting', 'slug' => 'premier-boosting'],
                ['name' => 'Faceit ELO', 'slug' => 'faceit-elo'],
                ['name' => 'Placements', 'slug' => 'placement-matches'],
                ['name' => 'Competitive', 'slug' => 'competitive'],
                ['name' => 'Wingman', 'slug' => 'wingman'],
            ],
            'deadlock' => [
                ['name' => 'Rank Boosting', 'slug' => 'rank-boosting'],
                ['name' => 'Coaching', 'slug' => 'coaching'],
                ['name' => 'Battle Pass', 'slug' => 'battle-pass'],
            ],
            'diablo-4' => [
                ['name' => 'Power Leveling', 'slug' => 'power-leveling'],
                ['name' => 'Paragon', 'slug' => 'paragon'],
                ['name' => 'Boss Kills', 'slug' => 'boss-kills'],
                ['name' => 'Glyph Leveling', 'slug' => 'glyph-leveling'],
                ['name' => 'Build Services', 'slug' => 'build-services'],
            ],
            'fragpunk' => [
                ['name' => 'Rank Boost', 'slug' => 'rank-boosting'],
                ['name' => 'Wins', 'slug' => 'wins'],
                ['name' => 'Placements', 'slug' => 'placement-matches'],
                ['name' => 'Weapon Leveling', 'slug' => 'weapon-leveling'],
            ],
            'heroes-of-the-storm' => [
                ['name' => 'Rank Boosting', 'slug' => 'rank-boosting'],
                ['name' => 'Placements', 'slug' => 'placement-matches'],
                ['name' => 'Quick Match Wins', 'slug' => 'quick-match-wins'],
            ],
            'league-of-legends' => [
                ['name' => 'Division Boosting', 'slug' => 'division-boosting'],
                ['name' => 'Placements', 'slug' => 'placement-matches'],
                ['name' => 'Coaching', 'slug' => 'coaching'],
                ['name' => 'Arena', 'slug' => 'arena'],
                ['name' => 'Challenges', 'slug' => 'challenges'],
            ],
            'marvel-rivals' => [
                ['name' => 'Rank Boosting', 'slug' => 'rank-boosting'],
                ['name' => 'Hero Proficiency', 'slug' => 'hero-proficiency'],
                ['name' => 'Skin Unlocks', 'slug' => 'skin-unlocks'],
                ['name' => 'Missions', 'slug' => 'missions'],
            ],
            'modern-warfare-3' => [
                ['name' => 'Camos', 'slug' => 'camos'],
                ['name' => 'Unlock Services', 'slug' => 'unlock-services'],
                ['name' => 'KD Boosting', 'slug' => 'kd-boosting'],
                ['name' => 'Operator Unlocks', 'slug' => 'operator-unlocks'],
            ],
            'new-world' => [
                ['name' => 'Leveling', 'slug' => 'leveling'],
                ['name' => 'Weapon Mastery', 'slug' => 'weapon-mastery'],
            ],
            'overwatch-2' => [
                ['name' => 'Rank Boosting', 'slug' => 'rank-boosting'],
                ['name' => 'Top 500', 'slug' => 'top-500'],
                ['name' => 'Coaching', 'slug' => 'coaching'],
                ['name' => 'Hero Progression', 'slug' => 'hero-progression'],
            ],
            'rainbow-6-siege-x' => [
                ['name' => 'Rank Boost', 'slug' => 'rank-boosting'],
                ['name' => 'Battle Pass', 'slug' => 'battle-pass'],
                ['name' => 'Cup Boosting', 'slug' => 'cup-boosting'],
            ],
            'rocket-league' => [
                ['name' => 'Rank Boost', 'slug' => 'rank-boosting'],
                ['name' => 'Placements', 'slug' => 'placement-matches'],
                ['name' => 'Tournament Boosting', 'slug' => 'tournament-boosting'],
            ],
            'tft' => [
                ['name' => 'Divisions', 'slug' => 'divisions'],
                ['name' => 'Placements', 'slug' => 'placement-matches'],
                ['name' => 'Hyper Roll', 'slug' => 'hyper-roll'],
                ['name' => 'Double Up', 'slug' => 'double-up'],
            ],
            'valorant' => [
                ['name' => 'Rank Boost', 'slug' => 'rank-boosting'],
                ['name' => 'Ranked Wins', 'slug' => 'ranked-wins'],
                ['name' => 'Placements', 'slug' => 'placement-matches'],
                ['name' => 'Radiant Boost', 'slug' => 'radiant-boost'],
            ],
            'wild-rift' => [
                ['name' => 'Divisions', 'slug' => 'divisions'],
                ['name' => 'Wins', 'slug' => 'wins'],
                ['name' => 'Legendary Queue', 'slug' => 'legendary-queue'],
            ],
        ];
    }
}
