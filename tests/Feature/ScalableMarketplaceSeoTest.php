<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameService;
use App\Support\GameCatalog;
use App\Support\Seo\MarketplaceSeo;
use Database\Seeders\BlogArticleSeeder;
use Database\Seeders\GameCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScalableMarketplaceSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_game_pages_render_unique_titles_and_descriptions(): void
    {
        $this->seed(GameCatalogSeeder::class);

        $titles = [];
        $descriptions = [];
        $catalog = app(GameCatalog::class);
        $seoBuilder = app(MarketplaceSeo::class);

        Game::query()
            ->where('status', Game::STATUS_PUBLISHED)
            ->orderBy('slug')
            ->get()
            ->each(function (Game $game) use (&$titles, &$descriptions, $catalog, $seoBuilder): void {
                $seo = $seoBuilder->game($catalog->game($game->slug));
                $title = $seo['title'];
                $description = $seo['description'];

                $this->assertNotContains($title, $titles, "Duplicate title for {$game->slug}.");
                $this->assertNotContains($description, $descriptions, "Duplicate description for {$game->slug}.");
                $this->assertLessThanOrEqual(130, mb_strlen($description), $description);

                $titles[] = $title;
                $descriptions[] = $description;
            });

        $this->get(route('game.show', ['game' => 'modern-warfare-3']))
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('game.show', ['game' => 'modern-warfare-3']).'">', false);
    }

    public function test_seeded_service_pages_render_unique_titles_descriptions_and_social_tags(): void
    {
        $this->seed(GameCatalogSeeder::class);

        $titles = [];
        $descriptions = [];
        $catalog = app(GameCatalog::class);
        $seoBuilder = app(MarketplaceSeo::class);

        GameService::query()
            ->with('game')
            ->where('status', GameService::STATUS_PUBLISHED)
            ->whereHas('game', fn ($query) => $query->where('status', Game::STATUS_PUBLISHED))
            ->orderBy('slug')
            ->get()
            ->each(function (GameService $service) use (&$titles, &$descriptions, $catalog, $seoBuilder): void {
                $game = $catalog->game($service->game->slug);
                $activeService = collect($game['services'] ?? [])->firstWhere('slug', $service->slug);

                $this->assertIsArray($activeService, "Expected catalog payload for {$service->game->slug}/{$service->slug}.");

                $seo = $seoBuilder->service($game, $activeService);
                $title = $seo['title'];
                $description = $seo['description'];

                $this->assertNotContains($title, $titles, "Duplicate title for {$service->game->slug}/{$service->slug}.");
                $this->assertNotContains($description, $descriptions, "Duplicate description for {$service->game->slug}/{$service->slug}.");
                $this->assertLessThanOrEqual(130, mb_strlen($description), $description);

                $titles[] = $title;
                $descriptions[] = $description;
            });

        foreach ([
            ['game' => 'valorant', 'service' => 'radiant-boost', 'title' => 'Buy Radiant Boost for VALORANT | GGWP-Boost'],
            ['game' => 'apex-legends', 'service' => 'predator-boost', 'title' => 'Apex Predator Boost Service | GGWP-Boost'],
            ['game' => 'cs2', 'service' => 'faceit-elo', 'title' => 'CS2 Faceit ELO Boost | GGWP-Boost'],
            ['game' => 'modern-warfare-3', 'service' => 'camos', 'title' => 'MW3 Camos Unlock Service | GGWP-Boost'],
        ] as $case) {
            $this->get(route('game.services.show', ['game' => $case['game'], 'service' => $case['service']]))
                ->assertOk()
                ->assertSee('<title>'.$case['title'].'</title>', false)
                ->assertSee('<meta property="og:title"', false)
                ->assertSee('<meta name="twitter:title"', false)
                ->assertSee('<meta name="twitter:card" content="summary"', false);
        }
    }

    public function test_category_pages_render_breadcrumbs_canonical_and_structured_data(): void
    {
        $this->seed(GameCatalogSeeder::class);

        $this->get(route('games.categories.show', ['category' => 'fps']))
            ->assertOk()
            ->assertSeeText('FPS Boosting Services')
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('<link rel="canonical" href="'.route('games.categories.show', ['category' => 'fps']).'">', false)
            ->assertSee('application/ld+json', false)
            ->assertSee(route('game.show', ['game' => 'modern-warfare-3']), false)
            ->assertSee(route('game.services.show', ['game' => 'modern-warfare-3', 'service' => 'camos']), false);
    }

    public function test_sitemap_uses_canonical_category_game_service_and_blog_urls(): void
    {
        $this->seed([GameCatalogSeeder::class, BlogArticleSeeder::class]);

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertSee(route('home'), false)
            ->assertSee(route('games.categories.show', ['category' => 'fps']), false)
            ->assertSee(route('game.show', ['game' => 'modern-warfare-3']), false)
            ->assertSee(route('game.services.show', ['game' => 'modern-warfare-3', 'service' => 'camos']), false)
            ->assertSee(route('blog.show', ['slug' => 'is-valorant-boosting-safe']), false)
            ->assertDontSee(route('games.show', ['game' => 'modern-warfare-3']), false)
            ->assertDontSee(route('games.services.show', ['game' => 'modern-warfare-3', 'service' => 'camos']), false);
    }

    public function test_robots_txt_is_dynamic_and_points_to_current_sitemap(): void
    {
        $this->get(route('robots'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('User-agent: *', false)
            ->assertSee('Disallow: /admin', false)
            ->assertSee('Disallow: /user', false)
            ->assertSee('Sitemap: '.route('sitemap'), false);
    }
}
