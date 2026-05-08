<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameAddon;
use App\Models\GameService;
use App\Models\ServicePricingRule;
use App\Models\User;
use Database\Seeders\GameCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MvpProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_and_shows_seeded_featured_games(): void
    {
        $this->seed(GameCatalogSeeder::class);

        $this->get(route('home'))
            ->assertOk()
            ->assertViewHas('featuredGames', function (array $games): bool {
                $slugs = collect($games)->pluck('slug');

                return $slugs->contains('valorant')
                    && $slugs->contains('league-of-legends')
                    && $slugs->contains('cs2');
            })
            ->assertSeeText('Featured games and services')
            ->assertSeeText('VALORANT')
            ->assertSeeText('League of Legends')
            ->assertSeeText('CS2');
    }

    public function test_seeded_game_and_service_pages_render_and_missing_pages_return_404(): void
    {
        $this->seed(GameCatalogSeeder::class);

        $this->get(route('game.show', ['game' => 'cs2']))
            ->assertOk()
            ->assertSeeText('CS2');

        $this->get(route('game.services.show', ['game' => 'cs2', 'service' => 'faceit-elo']))
            ->assertOk()
            ->assertSeeText('Faceit ELO');

        $this->get(route('game.show', ['game' => 'missing-game']))->assertNotFound();
        $this->get(route('game.services.show', ['game' => 'cs2', 'service' => 'missing-service']))->assertNotFound();
    }

    public function test_service_and_addon_must_belong_to_the_selected_parent(): void
    {
        $selectedGame = Game::factory()->create([
            'slug' => 'omega-arena',
            'name' => 'Omega Arena',
            'status' => Game::STATUS_PUBLISHED,
        ]);
        $otherGame = Game::factory()->create([
            'slug' => 'delta-arena',
            'name' => 'Delta Arena',
            'status' => Game::STATUS_PUBLISHED,
        ]);
        $selectedService = GameService::factory()->for($selectedGame, 'game')->create([
            'slug' => 'coaching-sprint',
            'name' => 'Coaching Sprint',
            'kind' => 'coaching',
            'status' => GameService::STATUS_PUBLISHED,
        ]);
        $otherService = GameService::factory()->for($otherGame, 'game')->create([
            'slug' => 'foreign-service',
            'name' => 'Foreign Service',
            'kind' => 'coaching',
            'status' => GameService::STATUS_PUBLISHED,
        ]);
        $addon = GameAddon::factory()->for($selectedGame, 'game')->create([
            'slug' => 'priority-order',
            'label' => 'Priority Order',
            'status' => GameAddon::STATUS_PUBLISHED,
        ]);

        $this->basePricingRule($selectedGame, $selectedService, 30);
        $this->basePricingRule($otherGame, $otherService, 30);
        $otherService->addons()->attach($addon->id, ['status' => GameAddon::STATUS_PUBLISHED]);

        $this->get(route('game.services.show', [
            'game' => $selectedGame->slug,
            'service' => $otherService->slug,
        ]))->assertNotFound();

        $this->postJson(route('pricing.calculate'), [
            'gameSlug' => $selectedGame->slug,
            'serviceSlug' => $selectedService->slug,
            'serviceType' => $selectedService->name,
            'selectedAddons' => [$addon->label],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('validationErrors.selectedAddons.0', "{$addon->label} is not available for this service.");
    }

    public function test_admin_routes_require_authentication_and_authorization(): void
    {
        $routes = [
            route('admin-dashboard'),
            route('admin-marketplace.games.index'),
            route('admin-pricing.index'),
        ];

        foreach ($routes as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'account_status' => 'active',
        ]);

        foreach ($routes as $url) {
            $this->actingAs($customer)->get($url)->assertForbidden();
        }
    }

    public function test_sitemap_includes_active_seeded_games_and_services(): void
    {
        $this->seed(GameCatalogSeeder::class);

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertSee(route('game.show', ['game' => 'cs2']), false)
            ->assertSee(route('game.services.show', ['game' => 'cs2', 'service' => 'faceit-elo']), false)
            ->assertSee(route('game.show', ['game' => 'league-of-legends']), false)
            ->assertSee(route('game.services.show', ['game' => 'league-of-legends', 'service' => 'division-boosting']), false);
    }

    public function test_inactive_games_and_services_are_hidden(): void
    {
        $draftGame = Game::factory()->create([
            'slug' => 'draft-arena',
            'name' => 'Draft Arena',
            'status' => Game::STATUS_DRAFT,
            'metadata' => ['featured' => true],
        ]);
        $publishedGame = Game::factory()->create([
            'slug' => 'visible-arena',
            'name' => 'Visible Arena',
            'status' => Game::STATUS_PUBLISHED,
        ]);
        $draftService = GameService::factory()->for($publishedGame, 'game')->create([
            'slug' => 'draft-service',
            'name' => 'Draft Service',
            'status' => GameService::STATUS_DRAFT,
            'metadata' => ['homepage_featured' => true],
        ]);

        $this->get(route('game.show', ['game' => $draftGame->slug]))->assertNotFound();
        $this->get(route('game.services.show', [
            'game' => $publishedGame->slug,
            'service' => $draftService->slug,
        ]))->assertNotFound();

        $this->get(route('home'))
            ->assertOk()
            ->assertViewHas('featuredGames', fn (array $games): bool => ! collect($games)->contains('slug', $draftGame->slug))
            ->assertViewHas('homepageFeaturedServices', fn (array $services): bool => ! collect($services)->contains('slug', $draftService->slug));

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertDontSee(route('game.show', ['game' => $draftGame->slug]), false)
            ->assertDontSee(route('game.services.show', [
                'game' => $publishedGame->slug,
                'service' => $draftService->slug,
            ]), false);
    }

    protected function basePricingRule(Game $game, GameService $service, float $amount): ServicePricingRule
    {
        return ServicePricingRule::factory()
            ->for($game, 'game')
            ->for($service, 'service')
            ->create([
                'slug' => $service->slug.'-base',
                'name' => $service->name.' base pricing',
                'scope' => ServicePricingRule::SCOPE_BASE,
                'calculator_key' => 'flat_service',
                'pricing_type' => ServicePricingRule::PRICING_FIXED,
                'amount' => $amount,
                'status' => ServicePricingRule::STATUS_PUBLISHED,
            ]);
    }
}
