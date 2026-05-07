<?php

namespace Tests\Feature;

use App\Actions\CreateOrderAction;
use App\Data\Payments\PaymentCheckoutData;
use App\Models\Game;
use App\Models\GameRank;
use App\Models\GameService;
use App\Models\Order;
use App\Models\PendingCheckoutRecord;
use App\Models\User;
use App\Services\Payments\PendingCheckoutStore;
use App\Support\Pricing\PricingEngineManager;
use Database\Seeders\GameCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiGameMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pricing_config_is_game_aware_and_defaults_to_valorant(): void
    {
        $this->getJson(route('pricing.config'))
            ->assertOk()
            ->assertJsonPath('gameSlug', 'valorant')
            ->assertJsonPath('game.slug', 'valorant')
            ->assertJsonPath('pricingPreview.gameSlug', 'valorant');
    }

    public function test_price_calculation_accepts_game_slug_without_changing_valorant_pricing(): void
    {
        $this->postJson(route('pricing.calculate'), [
            'gameSlug' => 'valorant',
            'serviceType' => 'Rank Boosting',
            'currentDivision' => 'Gold II',
            'desiredDivision' => 'Platinum II',
            'currentRR' => 55,
            'avgRRPerWin' => '16 OR LOWER',
            'region' => 'NA',
            'platform' => 'PC',
            'boostMode' => 'Account Shared',
            'selectedAddons' => ['Solo-Queue Only', 'Offline Mode'],
        ])
            ->assertOk()
            ->assertJsonPath('gameSlug', 'valorant')
            ->assertJsonPath('game', 'Valorant')
            ->assertJsonPath('finalPrice', 41.29);
    }

    public function test_checkout_storage_records_keep_first_class_game_references(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $pricedPayload = app(PricingEngineManager::class)->calculateOrFail([
            'gameSlug' => 'valorant',
            'serviceType' => 'Placement Matches',
            'currentDivision' => 'Iron I',
            'numberOfPlacementGames' => 1,
            'region' => 'EU',
            'platform' => 'PC',
            'boostMode' => 'Account Shared',
            'selectedAddons' => [],
        ]);

        $checkoutData = new PaymentCheckoutData(
            requestData: [
                'firstName' => 'Demo',
                'lastName' => 'Customer',
                'email' => $customer->email,
                'contactMethod' => 'email',
                'whatsapp' => null,
                'discord' => null,
            ],
            orderPayload: $pricedPayload,
            paymentMethod: 'stripe',
            priceCents: (int) round($pricedPayload['finalPrice'] * 100),
            total: $pricedPayload['finalPrice'],
            subtotal: $pricedPayload['finalPrice'],
        );

        $pendingCheckout = app(PendingCheckoutStore::class)->create($customer->id, $checkoutData);
        $pendingRecord = PendingCheckoutRecord::query()->where('token', $pendingCheckout->token)->firstOrFail();
        $order = app(CreateOrderAction::class)->execute($customer->id, $checkoutData, [
            'payment_status' => 'paid',
            'payment_reference' => 'test:multi-game',
        ]);
        $valorant = Game::query()->where('slug', 'valorant')->firstOrFail();

        $this->assertSame($valorant->id, $pendingRecord->game_id);
        $this->assertSame($valorant->id, $order->game_id);
        $this->assertSame('valorant', $order->gameSlug());
        $this->assertSame('valorant', data_get($order->details, 'order.gameSlug'));
        $this->assertSame('Valorant', data_get($order->metadata, 'game.name'));
        $this->assertSame(1, Order::query()->count());
    }

    public function test_game_landing_route_renders_for_published_catalog_games(): void
    {
        $game = Game::query()->updateOrCreate(
            ['slug' => 'league-of-legends'],
            [
                'name' => 'League of Legends',
                'short_name' => 'League',
                'status' => Game::STATUS_PUBLISHED,
                'sort_order' => 20,
                'assets' => [],
                'metadata' => [],
            ],
        );
        GameService::query()->updateOrCreate(
            ['game_id' => $game->id, 'slug' => 'division-boost'],
            [
                'name' => 'Division Boost',
                'kind' => 'rank_boost',
                'status' => Game::STATUS_PUBLISHED,
                'sort_order' => 1,
                'config' => [],
            ],
        );
        GameRank::query()->updateOrCreate(
            ['game_id' => $game->id, 'slug' => 'gold-iv'],
            [
                'label' => 'Gold IV',
                'division' => 'Gold',
                'sort_order' => 1,
                'metadata' => [],
            ],
        );

        $this->get(route('games.show', ['game' => 'league-of-legends']))
            ->assertOk()
            ->assertSeeText('Fast, Safe League Rank Boosting Built Around Your Goal.')
            ->assertSeeText('League Boost Services Pricing')
            ->assertSee('"gameSlug":"league-of-legends"', false)
            ->assertSee('"Gold IV"', false);
    }

    public function test_homepage_exposes_dynamic_featured_games_and_services(): void
    {
        $game = Game::query()->updateOrCreate(
            ['slug' => 'league-of-legends'],
            [
                'name' => 'League of Legends',
                'short_name' => 'League',
                'description' => 'League rank boosting and placement services.',
                'status' => Game::STATUS_PUBLISHED,
                'sort_order' => 20,
                'assets' => [],
                'metadata' => ['featured' => true],
            ],
        );
        GameService::query()->updateOrCreate(
            ['game_id' => $game->id, 'slug' => 'division-boost'],
            [
                'name' => 'Division Boost',
                'kind' => 'rank_boost',
                'description' => 'Climb League divisions with a configured order.',
                'status' => Game::STATUS_PUBLISHED,
                'sort_order' => 1,
                'config' => [],
                'metadata' => ['homepage_featured' => true, 'popular' => true],
            ],
        );

        $this->get(route('home'))
            ->assertOk()
            ->assertViewHas('featuredGames', fn (array $games): bool => collect($games)->contains('slug', 'league-of-legends'))
            ->assertViewHas('homepageFeaturedServices', fn (array $services): bool => collect($services)->contains('slug', 'division-boost'))
            ->assertSeeText('Featured games and services')
            ->assertSeeText('League of Legends')
            ->assertSeeText('Division Boost');
    }

    public function test_public_marketplace_navigation_is_catalog_driven_and_grouped(): void
    {
        $this->seed(GameCatalogSeeder::class);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('id="marketplaceGamesDropdown"', false)
            ->assertSee('id="marketplaceServicesDropdown"', false)
            ->assertSeeTextInOrder([
                'Games',
                'FPS',
                'VALORANT',
                'CS2',
                'Apex Legends',
                'Overwatch 2',
                'Rainbow 6 Siege X',
                'Black Ops 6',
                'Battlefield 6',
                'Marvel Rivals',
                'FragPunk',
                'Deadlock',
                'MOBA',
                'League of Legends',
                'TFT',
                'Wild Rift',
                'Heroes of the Storm',
                'MMO / RPG',
                'Diablo 4',
                'New World',
                'Arc Raiders',
            ])
            ->assertSeeTextInOrder([
                'Services',
                'Rank Boosting',
                'Placements',
                'Coaching',
                'Power Leveling',
                'Unlock Services',
                'Battle Pass',
                'Weapon Leveling',
                'Challenges',
                'Farming',
            ])
            ->assertSeeText('Order Now')
            ->assertSeeText('Live Chat')
            ->assertSee(route('games.show', ['game' => 'rainbow-6-siege-x']), false)
            ->assertSee(route('games.show', ['game' => 'arc-raiders']), false)
            ->assertSee(route('games.services.show', ['game' => 'valorant', 'service' => 'rank-boosting']), false)
            ->assertSee(route('games.services.show', ['game' => 'valorant', 'service' => 'placement-matches']), false)
            ->assertSee(route('games.services.show', ['game' => 'diablo-4', 'service' => 'power-leveling']), false)
            ->assertSee(route('games.services.show', ['game' => 'diablo-4', 'service' => 'farming']), false);
    }

    public function test_marketplace_navigation_marks_current_game_and_service_links_active(): void
    {
        $this->seed(GameCatalogSeeder::class);

        $this->get(route('games.show', ['game' => 'black-ops-6']))
            ->assertOk()
            ->assertSee(route('games.show', ['game' => 'black-ops-6']), false)
            ->assertSee('aria-current="page"', false);

        $this->get(route('games.services.show', ['game' => 'black-ops-6', 'service' => 'weapon-leveling']))
            ->assertOk()
            ->assertSee(route('games.services.show', ['game' => 'black-ops-6', 'service' => 'weapon-leveling']), false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_game_landing_route_404s_missing_or_draft_games(): void
    {
        Game::query()->updateOrCreate(
            ['slug' => 'draft-game'],
            [
                'name' => 'Draft Game',
                'short_name' => 'Draft',
                'status' => Game::STATUS_DRAFT,
                'sort_order' => 99,
                'assets' => [],
                'metadata' => [],
            ],
        );

        $this->get(route('games.show', ['game' => 'missing-game']))->assertNotFound();
        $this->get(route('games.show', ['game' => 'draft-game']))->assertNotFound();
    }

    public function test_service_route_renders_published_services_and_keeps_pricing_config_functional(): void
    {
        $game = Game::query()->updateOrCreate(
            ['slug' => 'league-of-legends'],
            [
                'name' => 'League of Legends',
                'short_name' => 'League',
                'status' => Game::STATUS_PUBLISHED,
                'sort_order' => 20,
                'assets' => [],
                'metadata' => [],
            ],
        );
        GameService::query()->updateOrCreate(
            ['game_id' => $game->id, 'slug' => 'division-boost'],
            [
                'name' => 'Division Boost',
                'kind' => 'rank_boost',
                'description' => 'Climb divisions with live pricing and checkout.',
                'status' => Game::STATUS_PUBLISHED,
                'sort_order' => 1,
                'config' => [],
                'metadata' => [],
            ],
        );

        $this->get(route('games.services.show', ['game' => 'league-of-legends', 'service' => 'division-boost']))
            ->assertOk()
            ->assertSeeText('League Division Boost')
            ->assertSeeText('Division Boost pricing');

        $this->getJson(route('games.pricing.config', ['game' => 'league-of-legends']))
            ->assertOk()
            ->assertJsonPath('gameSlug', 'league-of-legends');
    }

    public function test_service_route_404s_missing_draft_and_wrong_game_services(): void
    {
        $league = Game::query()->updateOrCreate(
            ['slug' => 'league-of-legends'],
            [
                'name' => 'League of Legends',
                'short_name' => 'League',
                'status' => Game::STATUS_PUBLISHED,
                'sort_order' => 20,
                'assets' => [],
                'metadata' => [],
            ],
        );
        $cs2 = Game::query()->updateOrCreate(
            ['slug' => 'cs2'],
            [
                'name' => 'CS2',
                'short_name' => 'CS2',
                'status' => Game::STATUS_PUBLISHED,
                'sort_order' => 30,
                'assets' => [],
                'metadata' => [],
            ],
        );
        GameService::query()->updateOrCreate(
            ['game_id' => $league->id, 'slug' => 'draft-service'],
            [
                'name' => 'Draft Service',
                'kind' => 'rank_boost',
                'status' => Game::STATUS_DRAFT,
                'sort_order' => 1,
                'config' => [],
                'metadata' => [],
            ],
        );
        GameService::query()->updateOrCreate(
            ['game_id' => $cs2->id, 'slug' => 'faceit-elo'],
            [
                'name' => 'Faceit ELO',
                'kind' => 'faceit_elo',
                'status' => Game::STATUS_PUBLISHED,
                'sort_order' => 1,
                'config' => [],
                'metadata' => [],
            ],
        );

        $this->get(route('games.services.show', ['game' => 'league-of-legends', 'service' => 'missing-service']))->assertNotFound();
        $this->get(route('games.services.show', ['game' => 'league-of-legends', 'service' => 'draft-service']))->assertNotFound();
        $this->get(route('games.services.show', ['game' => 'league-of-legends', 'service' => 'faceit-elo']))->assertNotFound();
    }

    public function test_missing_marketplace_pages_render_seo_safe_404(): void
    {
        $this->get(route('games.show', ['game' => 'missing-game']))
            ->assertNotFound()
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false);
    }

    public function test_sitemap_includes_published_non_default_game_pages(): void
    {
        $game = Game::query()->updateOrCreate(
            ['slug' => 'league-of-legends'],
            [
                'name' => 'League of Legends',
                'short_name' => 'League',
                'status' => Game::STATUS_PUBLISHED,
                'sort_order' => 20,
                'assets' => [],
                'metadata' => [],
            ],
        );
        $visibleService = GameService::query()->updateOrCreate(
            ['game_id' => $game->id, 'slug' => 'division-boost'],
            [
                'name' => 'Division Boost',
                'kind' => 'rank_boost',
                'status' => Game::STATUS_PUBLISHED,
                'sort_order' => 1,
                'config' => [],
                'metadata' => [],
            ],
        );
        $hiddenService = GameService::query()->updateOrCreate(
            ['game_id' => $game->id, 'slug' => 'private-service'],
            [
                'name' => 'Private Service',
                'kind' => 'coaching',
                'status' => Game::STATUS_PUBLISHED,
                'sort_order' => 2,
                'config' => [],
                'metadata' => [],
            ],
        );
        $hiddenService->seoMetadata()->updateOrCreate(
            ['context' => 'default'],
            [
                'robots' => 'noindex,follow',
                'include_in_sitemap' => true,
                'metadata' => [],
            ],
        );

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertSee(route('games.show', ['game' => 'league-of-legends']), false)
            ->assertSee(route('games.services.show', ['game' => 'league-of-legends', 'service' => $visibleService->slug]), false)
            ->assertDontSee(route('games.services.show', ['game' => 'league-of-legends', 'service' => $hiddenService->slug]), false);
    }
}
