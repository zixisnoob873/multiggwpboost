<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GameAddon;
use App\Models\GameService;
use App\Services\Checkout\CheckoutSelectionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutSelectionResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_canonicalizes_valid_game_service_and_addons(): void
    {
        [$game, $service] = $this->catalogService();
        $addon = $this->addon($game, $service, 'Priority Queue');

        $selection = app(CheckoutSelectionResolver::class)->canonicalizePayload([
            'gameSlug' => $game->slug,
            'serviceSlug' => $service->slug,
            'selectedAddons' => [$addon->slug],
        ]);

        $this->assertSame([], $selection['errors']);
        $this->assertSame($game->slug, $selection['payload']['gameSlug']);
        $this->assertSame($service->slug, $selection['payload']['serviceSlug']);
        $this->assertSame($service->name, $selection['payload']['serviceType']);
        $this->assertSame(['Priority Queue'], $selection['payload']['addons']);
        $this->assertSame('Priority Queue', $selection['addons'][0]['label']);
    }

    public function test_resolver_preserves_legacy_valorant_payloads_without_explicit_game_or_service(): void
    {
        $selection = app(CheckoutSelectionResolver::class)->canonicalizePayload([
            'serviceType' => 'Rank Boosting',
            'currentDivision' => 'Gold II',
            'desiredDivision' => 'Platinum II',
            'selectedAddons' => ['Solo-Queue Only'],
        ]);

        $this->assertSame([], $selection['errors']);
        $this->assertSame('valorant', $selection['payload']['gameSlug']);
        $this->assertSame('rank-boosting', $selection['payload']['serviceSlug']);
        $this->assertSame('Rank Boosting', $selection['payload']['serviceType']);
        $this->assertSame(['Solo-Queue Only'], $selection['payload']['addons']);
    }

    public function test_resolver_rejects_service_from_a_different_game(): void
    {
        [$game] = $this->catalogService(slug: 'first-game');
        [, $otherService] = $this->catalogService(slug: 'second-game', serviceSlug: 'other-service');

        $selection = app(CheckoutSelectionResolver::class)->canonicalizePayload([
            'gameSlug' => $game->slug,
            'serviceSlug' => $otherService->slug,
            'serviceType' => $otherService->name,
        ]);

        $this->assertSame(['Select a valid service for the selected game.'], $selection['errors']['serviceSlug']);
    }

    public function test_resolver_rejects_addons_not_attached_to_the_selected_service(): void
    {
        [$game, $service] = $this->catalogService();
        GameAddon::factory()->create([
            'game_id' => $game->id,
            'slug' => 'unattached-addon',
            'label' => 'Unattached Addon',
            'status' => Game::STATUS_PUBLISHED,
        ]);

        $selection = app(CheckoutSelectionResolver::class)->canonicalizePayload([
            'gameSlug' => $game->slug,
            'serviceSlug' => $service->slug,
            'selectedAddons' => ['Unattached Addon'],
        ]);

        $this->assertSame(['Unattached Addon is not available for this service.'], $selection['errors']['selectedAddons']);
    }

    protected function catalogService(string $slug = 'test-game', string $serviceSlug = 'test-service'): array
    {
        $game = Game::factory()->create([
            'slug' => $slug,
            'name' => str($slug)->headline()->value(),
            'status' => Game::STATUS_PUBLISHED,
        ]);
        $service = GameService::factory()->create([
            'game_id' => $game->id,
            'slug' => $serviceSlug,
            'name' => str($serviceSlug)->headline()->value(),
            'kind' => 'coaching',
            'status' => Game::STATUS_PUBLISHED,
        ]);

        return [$game, $service];
    }

    protected function addon(Game $game, GameService $service, string $label): GameAddon
    {
        $addon = GameAddon::factory()->create([
            'game_id' => $game->id,
            'slug' => str($label)->slug(),
            'label' => $label,
            'status' => Game::STATUS_PUBLISHED,
        ]);

        $service->addons()->attach($addon->id, [
            'status' => Game::STATUS_PUBLISHED,
            'sort_order' => 1,
        ]);

        return $addon;
    }
}
