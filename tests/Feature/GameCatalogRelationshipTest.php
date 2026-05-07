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

        $this->assertEqualsCanonicalizing([
            'apex-legends',
            'arc-raiders',
            'battlefield-6',
            'black-ops-6',
            'cs2',
            'deadlock',
            'diablo-4',
            'fragpunk',
            'heroes-of-the-storm',
            'league-of-legends',
            'marvel-rivals',
            'new-world',
            'overwatch-2',
            'rainbow-6-siege-x',
            'tft',
            'valorant',
            'wild-rift',
        ], Game::query()->pluck('slug')->sort()->values()->all());

        foreach (['fps', 'mmo-rpg', 'moba'] as $categorySlug) {
            $this->assertDatabaseHas('game_categories', ['slug' => $categorySlug]);
        }

        $seededServiceNames = GameService::query()->pluck('name')->unique()->values()->all();

        foreach ([
            'Battle Pass Completion',
            'Challenges',
            'Coaching',
            'Faceit ELO',
            'Farming',
            'Placement Matches',
            'Power Leveling',
            'Predator Boost',
            'Radiant Boost',
            'Rank Boosting',
            'Unlock Services',
            'Weapon Leveling',
        ] as $serviceName) {
            $this->assertContains($serviceName, $seededServiceNames);
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
        $this->assertGreaterThanOrEqual(17, SeoMetadata::query()->where('seoable_type', Game::class)->count());
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
}
