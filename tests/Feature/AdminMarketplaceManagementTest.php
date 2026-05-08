<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameAddon;
use App\Models\GameService;
use App\Models\ServicePricingRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMarketplaceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_admin_routes_are_protected(): void
    {
        $game = Game::factory()->create();
        $service = GameService::factory()->for($game)->create();
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'account_status' => 'active',
        ]);

        $this->get(route('admin-marketplace.games.index'))
            ->assertRedirect(route('login'));

        $this->post(route('admin-marketplace.games.store'), $this->gamePayload())
            ->assertRedirect(route('login'));

        $this->actingAs($customer)
            ->get(route('admin-marketplace.games.index'))
            ->assertForbidden();

        $this->actingAs($customer)
            ->post(route('admin-marketplace.games.store'), $this->gamePayload())
            ->assertForbidden();

        $this->actingAs($customer)
            ->patch(route('admin-marketplace.games.archive', $game))
            ->assertForbidden();

        $this->actingAs($customer)
            ->patch(route('admin-marketplace.services.update', $service), $this->servicePayload($game, [
                'addon_ids' => [],
            ]))
            ->assertForbidden();
    }

    public function test_admin_can_create_published_game_with_blank_slug_and_public_page_works(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('admin-marketplace.games.store'), $this->gamePayload([
                'name' => 'Omega Arena',
                'slug' => '',
                'status' => Game::STATUS_PUBLISHED,
            ]))
            ->assertRedirect();

        $game = Game::query()->where('slug', 'omega-arena')->firstOrFail();

        $this->assertSame('Omega Arena', $game->name);

        $this->get(route('game.show', ['game' => $game->slug]))
            ->assertOk()
            ->assertSee('Omega Arena');
    }

    public function test_admin_can_create_published_service_with_blank_slug_and_base_price_and_public_page_works(): void
    {
        $admin = $this->makeAdmin();
        $game = Game::factory()->create([
            'slug' => 'omega-arena',
            'name' => 'Omega Arena',
            'status' => Game::STATUS_PUBLISHED,
        ]);

        $this->actingAs($admin)
            ->post(route('admin-marketplace.services.store'), $this->servicePayload($game, [
                'name' => 'Coaching Sprint',
                'slug' => '',
                'kind' => 'coaching',
                'status' => GameService::STATUS_PUBLISHED,
                'base_price' => '29.99',
            ]))
            ->assertRedirect();

        $service = GameService::query()->where('game_id', $game->id)->where('slug', 'coaching-sprint')->firstOrFail();

        $this->assertDatabaseHas('service_pricing_rules', [
            'game_id' => $game->id,
            'service_id' => $service->id,
            'addon_id' => null,
            'scope' => ServicePricingRule::SCOPE_BASE,
            'amount' => '29.9900',
            'status' => ServicePricingRule::STATUS_PUBLISHED,
        ]);

        $this->get(route('game.services.show', ['game' => $game->slug, 'service' => $service->slug]))
            ->assertOk()
            ->assertSee('Coaching Sprint');
    }

    public function test_admin_can_create_an_addon_assign_it_to_a_service_and_pricing_exposes_it(): void
    {
        $admin = $this->makeAdmin();
        $game = Game::factory()->create([
            'slug' => 'omega-arena',
            'name' => 'Omega Arena',
            'status' => Game::STATUS_PUBLISHED,
        ]);
        $service = GameService::factory()->for($game)->create([
            'slug' => 'coaching-sprint',
            'name' => 'Coaching Sprint',
            'kind' => 'coaching',
            'status' => GameService::STATUS_PUBLISHED,
        ]);
        ServicePricingRule::factory()->for($game)->for($service, 'service')->create([
            'slug' => 'coaching-sprint-base',
            'scope' => ServicePricingRule::SCOPE_BASE,
            'calculator_key' => 'flat_service',
            'amount' => 30,
            'status' => ServicePricingRule::STATUS_PUBLISHED,
        ]);

        $this->actingAs($admin)
            ->post(route('admin-marketplace.addons.store'), $this->addonPayload($game, [
                'label' => 'Priority Order',
                'slug' => '',
                'pricing_type' => ServicePricingRule::PRICING_FIXED,
                'pricing_value' => '12.50',
                'service_ids' => [$service->id],
            ]))
            ->assertRedirect();

        $addon = GameAddon::query()->where('game_id', $game->id)->where('slug', 'priority-order')->firstOrFail();

        $this->assertDatabaseHas('game_service_addons', [
            'game_service_id' => $service->id,
            'game_addon_id' => $addon->id,
            'status' => GameAddon::STATUS_PUBLISHED,
        ]);

        $this->get(route('game.services.show', ['game' => $game->slug, 'service' => $service->slug]))
            ->assertOk()
            ->assertSee('Priority Order')
            ->assertSee('data-addon-value="Priority Order"', false);

        $this->postJson(route('pricing.calculate'), [
            'gameSlug' => $game->slug,
            'serviceSlug' => $service->slug,
            'serviceType' => $service->name,
            'selectedAddons' => ['Priority Order'],
        ])
            ->assertOk()
            ->assertJsonPath('addonBreakdown.0.label', 'Priority Order')
            ->assertJsonPath('pricing.addons', 12.5);
    }

    public function test_duplicate_slugs_are_rejected_in_the_correct_scope(): void
    {
        $admin = $this->makeAdmin();
        $game = Game::factory()->create(['slug' => 'omega-arena']);
        $otherGame = Game::factory()->create(['slug' => 'delta-arena']);
        GameService::factory()->for($game)->create(['slug' => 'coaching']);
        GameAddon::factory()->for($game)->create(['slug' => 'priority-order']);

        $this->actingAs($admin)
            ->from(route('admin-marketplace.games.create'))
            ->post(route('admin-marketplace.games.store'), $this->gamePayload([
                'slug' => 'omega-arena',
            ]))
            ->assertRedirect(route('admin-marketplace.games.create'))
            ->assertSessionHasErrors('slug');

        $this->actingAs($admin)
            ->from(route('admin-marketplace.services.create'))
            ->post(route('admin-marketplace.services.store'), $this->servicePayload($game, [
                'slug' => 'coaching',
            ]))
            ->assertRedirect(route('admin-marketplace.services.create'))
            ->assertSessionHasErrors('slug');

        $this->actingAs($admin)
            ->post(route('admin-marketplace.services.store'), $this->servicePayload($otherGame, [
                'slug' => 'coaching',
            ]))
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors('slug');

        $this->actingAs($admin)
            ->from(route('admin-marketplace.addons.create'))
            ->post(route('admin-marketplace.addons.store'), $this->addonPayload($game, [
                'slug' => 'priority-order',
            ]))
            ->assertRedirect(route('admin-marketplace.addons.create'))
            ->assertSessionHasErrors('slug');
    }

    public function test_admin_can_update_seo_metadata_for_game_and_service_pages(): void
    {
        $admin = $this->makeAdmin();
        $game = Game::factory()->create([
            'slug' => 'omega-arena',
            'name' => 'Omega Arena',
            'status' => Game::STATUS_PUBLISHED,
        ]);
        $service = GameService::factory()->for($game)->create([
            'slug' => 'coaching-sprint',
            'name' => 'Coaching Sprint',
            'kind' => 'coaching',
            'status' => GameService::STATUS_PUBLISHED,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin-marketplace.games.update', $game), $this->gamePayload([
                'name' => $game->name,
                'slug' => $game->slug,
                'meta_title' => 'Omega Arena SEO Title',
                'meta_description' => 'Omega Arena SEO description.',
            ]))
            ->assertRedirect(route('admin-marketplace.games.edit', $game));

        $this->actingAs($admin)
            ->patch(route('admin-marketplace.services.update', $service), $this->servicePayload($game, [
                'name' => $service->name,
                'slug' => $service->slug,
                'kind' => $service->kind,
                'meta_title' => 'Coaching Sprint SEO Title',
                'meta_description' => 'Coaching Sprint SEO description.',
            ]))
            ->assertRedirect(route('admin-marketplace.services.edit', $service));

        $this->get(route('game.show', ['game' => $game->slug]))
            ->assertOk()
            ->assertSee('Omega Arena SEO Title')
            ->assertSee('Omega Arena SEO description.');

        $this->get(route('game.services.show', ['game' => $game->slug, 'service' => $service->slug]))
            ->assertOk()
            ->assertSee('Coaching Sprint SEO Title')
            ->assertSee('Coaching Sprint SEO description.');
    }

    public function test_archived_games_and_services_return_not_found_publicly(): void
    {
        $archivedGame = Game::factory()->create([
            'slug' => 'archived-arena',
            'status' => Game::STATUS_ARCHIVED,
        ]);
        $publishedGame = Game::factory()->create([
            'slug' => 'published-arena',
            'status' => Game::STATUS_PUBLISHED,
        ]);
        $archivedService = GameService::factory()->for($publishedGame)->create([
            'slug' => 'archived-service',
            'status' => GameService::STATUS_ARCHIVED,
        ]);

        $this->get(route('game.show', ['game' => $archivedGame->slug]))
            ->assertNotFound();

        $this->get(route('game.services.show', [
            'game' => $publishedGame->slug,
            'service' => $archivedService->slug,
        ]))
            ->assertNotFound();
    }

    public function test_archived_and_unassigned_addons_are_not_selectable(): void
    {
        $game = Game::factory()->create([
            'slug' => 'omega-arena',
            'status' => Game::STATUS_PUBLISHED,
        ]);
        $service = GameService::factory()->for($game)->create([
            'slug' => 'coaching-sprint',
            'name' => 'Coaching Sprint',
            'kind' => 'coaching',
            'status' => GameService::STATUS_PUBLISHED,
        ]);
        $visibleAddon = GameAddon::factory()->for($game)->create([
            'slug' => 'priority-order',
            'label' => 'Priority Order',
            'status' => GameAddon::STATUS_PUBLISHED,
        ]);
        $archivedAddon = GameAddon::factory()->for($game)->create([
            'slug' => 'retired-add-on',
            'label' => 'Retired Add-on',
            'status' => GameAddon::STATUS_ARCHIVED,
        ]);
        GameAddon::factory()->for($game)->create([
            'slug' => 'unassigned-add-on',
            'label' => 'Unassigned Add-on',
            'status' => GameAddon::STATUS_PUBLISHED,
        ]);

        $service->addons()->attach($visibleAddon->id, ['status' => GameAddon::STATUS_PUBLISHED]);
        $service->addons()->attach($archivedAddon->id, ['status' => GameAddon::STATUS_PUBLISHED]);

        $this->get(route('game.services.show', ['game' => $game->slug, 'service' => $service->slug]))
            ->assertOk()
            ->assertSee('Priority Order')
            ->assertSee('data-addon-label="Priority Order"', false)
            ->assertDontSee('data-addon-label="Retired Add-on"', false)
            ->assertDontSee('data-addon-label="Unassigned Add-on"', false);

        $this->postJson(route('pricing.calculate'), [
            'gameSlug' => $game->slug,
            'serviceSlug' => $service->slug,
            'serviceType' => $service->name,
            'selectedAddons' => ['Retired Add-on'],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('validationErrors.selectedAddons.0', 'Select a valid addon for this service.');
    }

    protected function makeAdmin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'account_status' => 'active',
        ]);
    }

    protected function gamePayload(array $overrides = []): array
    {
        return array_merge([
            'game_category_id' => null,
            'name' => 'Test Arena',
            'short_name' => 'TA',
            'slug' => 'test-arena',
            'description' => 'A test marketplace game.',
            'status' => Game::STATUS_PUBLISHED,
            'sort_order' => 1,
            'featured' => '1',
            'meta_title' => null,
            'meta_description' => null,
        ], $overrides);
    }

    protected function servicePayload(Game $game, array $overrides = []): array
    {
        return array_merge([
            'game_id' => $game->id,
            'name' => 'Test Service',
            'slug' => 'test-service',
            'kind' => 'coaching',
            'description' => 'A test marketplace service.',
            'status' => GameService::STATUS_PUBLISHED,
            'sort_order' => 1,
            'homepage_featured' => '1',
            'base_price' => '19.00',
            'meta_title' => null,
            'meta_description' => null,
            'addon_ids' => [],
        ], $overrides);
    }

    protected function addonPayload(Game $game, array $overrides = []): array
    {
        return array_merge([
            'game_id' => $game->id,
            'label' => 'Test Add-on',
            'slug' => 'test-add-on',
            'description' => 'A test marketplace add-on.',
            'icon' => null,
            'status' => GameAddon::STATUS_PUBLISHED,
            'sort_order' => 1,
            'pricing_type' => ServicePricingRule::PRICING_FIXED,
            'pricing_value' => '5.00',
            'service_ids' => [],
        ], $overrides);
    }
}
