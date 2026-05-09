<?php

namespace Tests\Feature;

use Database\Seeders\GameCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeProductConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_uses_marketplace_catalog_surface(): void
    {
        $this->seed(GameCatalogSeeder::class);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSeeText('Premium Game Boosting Services for Every Competitive Title')
            ->assertSeeText('Order Now')
            ->assertSeeText('Browse Games')
            ->assertSee('data-conversion-cta="home-primary"', false)
            ->assertSeeText('Featured games and services')
            ->assertSeeText('VALORANT')
            ->assertSeeText('Rank Boost')
            ->assertSee(route('game.show', ['game' => 'valorant']), false)
            ->assertSee(route('game.services.show', ['game' => 'valorant', 'service' => 'rank-boosting']), false)
            ->assertSee('data-agent-selector-modal-root', false)
            ->assertDontSeeText('Radiant triangle')
            ->assertViewHas('featuredGames', fn (array $games): bool => collect($games)
                ->take(7)
                ->pluck('slug')
                ->values()
                ->all() === [
                    'valorant',
                    'league-of-legends',
                    'cs2',
                    'apex-legends',
                    'overwatch-2',
                    'black-ops-6',
                    'rocket-league',
                ])
            ->assertViewHas('featuredGames', fn (array $games): bool => collect($games)
                ->take(8)
                ->pluck('slug')
                ->values()
                ->all() === [
                    'valorant',
                    'league-of-legends',
                    'cs2',
                    'apex-legends',
                    'overwatch-2',
                    'black-ops-6',
                    'rocket-league',
                    'diablo-4',
                ]);
    }

    public function test_home_page_renders_cryptomus_verification_meta_tag(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<meta name="cryptomus" content="fdcccf04" />', false);
    }
}
