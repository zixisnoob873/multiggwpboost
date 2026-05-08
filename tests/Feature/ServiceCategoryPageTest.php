<?php

namespace Tests\Feature;

use Database\Seeders\GameCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ServiceCategoryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_rank_boosting_category_lists_requested_games_and_exact_service_links(): void
    {
        $this->seed(GameCatalogSeeder::class);

        $response = $this->get(route('services.categories.show', ['category' => 'rank-boosting']));

        $response->assertOk()
            ->assertViewIs('marketplace.service-category')
            ->assertSeeText('Rank Boosting Services')
            ->assertSeeText('VALORANT')
            ->assertSeeText('League of Legends')
            ->assertSeeText('CS2')
            ->assertSeeText('Apex Legends')
            ->assertSeeText('Overwatch 2')
            ->assertSee(route('game.services.show', ['game' => 'valorant', 'service' => 'rank-boosting']), false)
            ->assertSee(route('game.services.show', ['game' => 'league-of-legends', 'service' => 'division-boosting']), false)
            ->assertSee(route('game.services.show', ['game' => 'cs2', 'service' => 'premier-boosting']), false)
            ->assertSee(route('game.services.show', ['game' => 'apex-legends', 'service' => 'rank-boosting']), false)
            ->assertSee(route('game.services.show', ['game' => 'overwatch-2', 'service' => 'rank-boosting']), false)
            ->assertDontSee(route('game.services.show', ['game' => 'valorant', 'service' => 'radiant-boost']), false)
            ->assertViewHas('categoryServices', function (array $services): bool {
                return collect($services)->every(fn (array $service): bool => in_array($service['kind'], [
                    'rank_boost',
                    'ranked_boosting',
                    'division_boosting',
                    'divisions',
                    'premier_boosting',
                ], true))
                    && collect($services)->every(fn (array $service): bool => $service['ctaUrl'] === $service['url']);
            });
    }

    public function test_coaching_category_lists_supported_coaching_services_only(): void
    {
        $this->seed(GameCatalogSeeder::class);

        $this->get(route('services.categories.show', ['category' => 'coaching']))
            ->assertOk()
            ->assertSeeText('Coaching Services')
            ->assertSee(route('game.services.show', ['game' => 'league-of-legends', 'service' => 'coaching']), false)
            ->assertSee(route('game.services.show', ['game' => 'deadlock', 'service' => 'coaching']), false)
            ->assertSee(route('game.services.show', ['game' => 'overwatch-2', 'service' => 'coaching']), false)
            ->assertDontSee(route('game.services.show', ['game' => 'black-ops-6', 'service' => 'coaching']), false)
            ->assertDontSee(route('game.services.show', ['game' => 'modern-warfare-3', 'service' => 'coaching']), false)
            ->assertViewHas('categoryServices', fn (array $services): bool => collect($services)
                ->isNotEmpty()
                && collect($services)->every(fn (array $service): bool => $service['kind'] === 'coaching'));
    }

    #[DataProvider('serviceCategorySlugProvider')]
    public function test_service_category_pages_render_unique_seo_metadata(string $slug): void
    {
        $this->seed(GameCatalogSeeder::class);

        $response = $this->get(route('services.categories.show', ['category' => $slug]));
        $html = $response->assertOk()->getContent();

        $this->assertStringContainsString(
            '<link rel="canonical" href="'.route('services.categories.show', ['category' => $slug]).'">',
            $html
        );
        $this->assertLessThanOrEqual(130, mb_strlen($this->metaContent($html, 'description')));
        $this->assertNotSame('', $this->pageTitle($html));
    }

    public function test_service_category_seo_titles_and_descriptions_are_unique(): void
    {
        $this->seed(GameCatalogSeeder::class);

        $titles = [];
        $descriptions = [];

        foreach (array_column(self::serviceCategorySlugProvider(), 0) as $slug) {
            $html = $this->get(route('services.categories.show', ['category' => $slug]))
                ->assertOk()
                ->getContent();

            $title = $this->pageTitle($html);
            $description = $this->metaContent($html, 'description');

            $this->assertNotContains($title, $titles, "Duplicate title for {$slug}.");
            $this->assertNotContains($description, $descriptions, "Duplicate description for {$slug}.");

            $titles[] = $title;
            $descriptions[] = $description;
        }
    }

    public function test_missing_service_category_returns_404(): void
    {
        $this->seed(GameCatalogSeeder::class);

        $this->get('/services/not-real')->assertNotFound();
    }

    public function test_related_category_links_exclude_current_category(): void
    {
        $this->seed(GameCatalogSeeder::class);

        $this->get(route('services.categories.show', ['category' => 'rank-boosting']))
            ->assertOk()
            ->assertSee(route('services.categories.show', ['category' => 'coaching']), false)
            ->assertSee(route('services.categories.show', ['category' => 'battle-pass']), false)
            ->assertViewHas('relatedServiceCategories', fn (array $categories): bool => collect($categories)
                ->isNotEmpty()
                && collect($categories)->doesntContain('slug', 'rank-boosting')
                && collect($categories)->every(fn (array $category): bool => str_contains($category['url'], '/services/')));
    }

    public function test_sitemap_includes_service_category_pages(): void
    {
        $this->seed(GameCatalogSeeder::class);

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertSee(route('services.categories.show', ['category' => 'rank-boosting']), false)
            ->assertSee(route('services.categories.show', ['category' => 'coaching']), false)
            ->assertSee(route('services.categories.show', ['category' => 'weapon-leveling']), false);
    }

    public function test_service_category_structured_data_contains_collection_lists_breadcrumbs_and_faq(): void
    {
        $this->seed(GameCatalogSeeder::class);

        $schema = $this->structuredDataFrom(route('services.categories.show', ['category' => 'rank-boosting']));
        $graph = $schema['@graph'] ?? [];

        $this->assertNotEmpty($this->findGraphNode($graph, 'CollectionPage'));
        $this->assertNotEmpty($this->findGraphNode($graph, 'BreadcrumbList'));
        $this->assertNotEmpty($this->findGraphNode($graph, 'ItemList'));
        $this->assertNotEmpty($this->findGraphNode($graph, 'FAQPage'));
    }

    public static function serviceCategorySlugProvider(): array
    {
        return [
            ['rank-boosting'],
            ['coaching'],
            ['power-leveling'],
            ['unlock-services'],
            ['battle-pass'],
            ['weapon-leveling'],
        ];
    }

    protected function pageTitle(string $html): string
    {
        preg_match('/<title\b[^>]*>(.*?)<\/title>/is', $html, $matches);

        return html_entity_decode(strip_tags($matches[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    protected function metaContent(string $html, string $name): string
    {
        preg_match('/<meta\s+[^>]*name=["\']'.preg_quote($name, '/').'["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches);

        return html_entity_decode($matches[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    protected function structuredDataFrom(string $url): array
    {
        $response = $this->get($url);
        $response->assertOk();

        preg_match('/<script\b[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $response->getContent(), $matches);

        $this->assertNotEmpty($matches[1] ?? null, 'Expected JSON-LD script.');

        $schema = json_decode($matches[1], true);

        $this->assertIsArray($schema);

        return $schema;
    }

    protected function findGraphNode(array $graph, string $type): array
    {
        foreach ($graph as $node) {
            $types = (array) ($node['@type'] ?? []);

            if (in_array($type, $types, true)) {
                return $node;
            }
        }

        return [];
    }
}
