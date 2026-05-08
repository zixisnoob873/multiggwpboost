<?php

namespace Tests\Feature;

use App\Models\Game;
use Database\Seeders\GameCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameLandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_mvp_games_render_on_reusable_singular_game_template(): void
    {
        $this->seed(GameCatalogSeeder::class);

        foreach ([
            'valorant',
            'league-of-legends',
            'cs2',
            'apex-legends',
            'overwatch-2',
        ] as $slug) {
            $game = Game::query()->where('slug', $slug)->firstOrFail();

            $this->get(route('game.show', ['game' => $slug]))
                ->assertOk()
                ->assertViewIs('marketplace.game')
                ->assertSeeText($game->name.' Boosting Services')
                ->assertSeeText('Choose a service')
                ->assertSee('data-conversion-cta="game-primary"', false)
                ->assertSeeText('Available Services')
                ->assertSeeText('Why players choose GGWPBoost')
                ->assertSeeText('Order process')
                ->assertSee('application/ld+json', false)
                ->assertSee('<link rel="canonical" href="'.route('game.show', ['game' => $slug]).'">', false);
        }
    }

    public function test_game_service_cards_link_to_singular_service_urls(): void
    {
        $this->seed(GameCatalogSeeder::class);

        $this->get(route('game.show', ['game' => 'league-of-legends']))
            ->assertOk()
            ->assertSee(route('game.services.show', [
                'game' => 'league-of-legends',
                'service' => 'division-boosting',
            ]), false)
            ->assertSee(route('game.services.show', [
                'game' => 'league-of-legends',
                'service' => 'placement-matches',
            ]), false)
            ->assertSeeText('League of Legends Boosting Services')
            ->assertDontSeeText('VALORANT Boost Services Pricing')
            ->assertDontSeeText('Start My VALORANT Boost');
    }

    public function test_game_page_seo_metadata_is_unique_per_game(): void
    {
        $this->seed(GameCatalogSeeder::class);

        $this->get(route('game.show', ['game' => 'league-of-legends']))
            ->assertOk()
            ->assertSee('<meta property="og:title" content="League of Legends Boosting | GGWPBoost">', false)
            ->assertSee('<meta name="description" content="Compare League division boosting, placements, coaching, Arena, and challenge services.">', false)
            ->assertSee('<link rel="canonical" href="'.route('game.show', ['game' => 'league-of-legends']).'">', false);

        $this->get(route('game.show', ['game' => 'cs2']))
            ->assertOk()
            ->assertSee('<meta property="og:title" content="CS2 Boosting and Faceit ELO | GGWPBoost">', false)
            ->assertSee('<meta name="description" content="Order CS2 Premier boosting, Faceit ELO, placements, Competitive, and Wingman services.">', false)
            ->assertSee('<link rel="canonical" href="'.route('game.show', ['game' => 'cs2']).'">', false);
    }

    public function test_missing_singular_game_slug_returns_404(): void
    {
        $this->seed(GameCatalogSeeder::class);

        $this->get(route('game.show', ['game' => 'missing-game']))->assertNotFound();
    }

    public function test_valorant_rank_boost_alias_renders_reusable_service_template(): void
    {
        $this->seed(GameCatalogSeeder::class);

        $this->get('/game/valorant/rank-boost')
            ->assertOk()
            ->assertViewIs('marketplace.service')
            ->assertSeeText('VALORANT Rank Boost')
            ->assertSeeText('Pricing Calculator')
            ->assertSeeText('Addons Section')
            ->assertSeeText('How It Works')
            ->assertSeeText('Estimated Delivery')
            ->assertSeeText('Duo Queue')
            ->assertSeeText('Streamed Games')
            ->assertSeeText('Start Order')
            ->assertSeeText('Configure order')
            ->assertSee('data-service-mobile-cta', false)
            ->assertSee('data-service-calculator', false)
            ->assertSee('<link rel="canonical" href="'.route('game.services.show', [
                'game' => 'valorant',
                'service' => 'rank-boosting',
            ]).'">', false);

        $this->get('/game/valorant/placements')
            ->assertOk()
            ->assertViewIs('marketplace.service')
            ->assertSeeText('VALORANT Placements')
            ->assertSee('<link rel="canonical" href="'.route('game.services.show', [
                'game' => 'valorant',
                'service' => 'placement-matches',
            ]).'">', false);
    }

    public function test_seeded_fixed_service_pages_render_on_reusable_service_template(): void
    {
        $this->seed(GameCatalogSeeder::class);

        $this->get('/game/cs2/faceit-elo')
            ->assertOk()
            ->assertViewIs('marketplace.service')
            ->assertSeeText('CS2 Faceit ELO')
            ->assertSeeText('Faceit ELO pricing')
            ->assertSee('data-service-calculator', false);

        $this->get('/game/apex-legends/predator-boost')
            ->assertOk()
            ->assertViewIs('marketplace.service')
            ->assertSeeText('Apex Predator Boost')
            ->assertSeeText('Predator Boost pricing')
            ->assertSee('data-service-calculator', false);
    }
}
