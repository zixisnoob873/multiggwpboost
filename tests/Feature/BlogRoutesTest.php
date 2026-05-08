<?php

namespace Tests\Feature;

use App\Models\BlogArticle;
use Database\Seeders\BlogArticleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BlogRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_index_loads_and_lists_seeded_articles(): void
    {
        $this->seed(BlogArticleSeeder::class);

        $response = $this->get(route('blog.index'));

        $response->assertOk()
            ->assertSee('VALORANT Boosting Guides, Safety Advice, and Rank-Up Strategy')
            ->assertSee('Is Valorant Boosting Safe? The Real Risks, Trade-Offs, and Smarter Options')
            ->assertDontSee('Open Checkout')
            ->assertDontSee('Get Quote');
    }

    public function test_blog_category_and_tag_archives_filter_to_published_articles(): void
    {
        $published = BlogArticle::query()->create([
            'title' => 'Apex Ranked Archive Guide',
            'slug' => 'apex-ranked-archive-guide',
            'category_name' => 'Apex Ranked',
            'category_slug' => 'apex-ranked',
            'tags' => ['apex-legends', 'ranked'],
            'author_name' => 'GGWP-Boost Editorial Team',
            'excerpt' => 'Apex archive excerpt.',
            'intro' => 'Apex archive intro.',
            'body' => "## Apex Section\n\n".str_repeat('Apex ranked archive body. ', 10),
            'cta_label' => 'Explore Apex Rank Boosting',
            'cta_url' => '/game/apex-legends/rank-boosting',
            'status' => BlogArticle::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'include_in_sitemap' => true,
        ]);

        BlogArticle::query()->create([
            'title' => 'VALORANT Archive Guide',
            'slug' => 'valorant-archive-guide',
            'category_name' => 'VALORANT Guides',
            'category_slug' => 'valorant-guides',
            'tags' => ['valorant'],
            'excerpt' => 'Valorant archive excerpt.',
            'intro' => 'Valorant archive intro.',
            'body' => str_repeat('Valorant archive body. ', 20),
            'status' => BlogArticle::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'include_in_sitemap' => true,
        ]);

        BlogArticle::query()->create([
            'title' => 'Apex Draft Archive Guide',
            'slug' => 'apex-draft-archive-guide',
            'category_name' => 'Apex Ranked',
            'category_slug' => 'apex-ranked',
            'tags' => ['apex-legends', 'ranked'],
            'excerpt' => 'Draft archive excerpt.',
            'intro' => 'Draft archive intro.',
            'body' => str_repeat('Draft archive body. ', 20),
            'status' => BlogArticle::STATUS_DRAFT,
            'published_at' => now()->subDay(),
            'include_in_sitemap' => true,
        ]);

        BlogArticle::query()->create([
            'title' => 'Apex Scheduled Archive Guide',
            'slug' => 'apex-scheduled-archive-guide',
            'category_name' => 'Apex Ranked',
            'category_slug' => 'apex-ranked',
            'tags' => ['apex-legends', 'ranked'],
            'excerpt' => 'Scheduled archive excerpt.',
            'intro' => 'Scheduled archive intro.',
            'body' => str_repeat('Scheduled archive body. ', 20),
            'status' => BlogArticle::STATUS_PUBLISHED,
            'published_at' => now()->addDay(),
            'include_in_sitemap' => true,
        ]);

        $categoryResponse = $this->get(route('blog.category', ['category' => 'apex-ranked']));

        $categoryResponse->assertOk()
            ->assertSee('Apex Ranked Guides')
            ->assertSee($published->title)
            ->assertSee('Explore Apex Rank Boosting')
            ->assertSee(route('blog.index'), false)
            ->assertDontSee('VALORANT Archive Guide')
            ->assertDontSee('Apex Draft Archive Guide')
            ->assertDontSee('Apex Scheduled Archive Guide');

        $tagResponse = $this->get(route('blog.tag', ['tag' => 'ranked']));

        $tagResponse->assertOk()
            ->assertSee('Ranked Guides')
            ->assertSee($published->title)
            ->assertSee('Apex Ranked')
            ->assertDontSee('VALORANT Archive Guide')
            ->assertDontSee('Apex Draft Archive Guide')
            ->assertDontSee('Apex Scheduled Archive Guide');
    }

    #[DataProvider('requiredArticleSlugProvider')]
    public function test_required_seeded_article_routes_load(string $slug): void
    {
        $this->seed(BlogArticleSeeder::class);

        $article = BlogArticle::query()->where('slug', $slug)->firstOrFail();

        $response = $this->get(route('blog.show', ['slug' => $slug]));

        $response->assertOk()
            ->assertSee($article->title)
            ->assertSee('<script nonce=', false)
            ->assertSee('application/ld+json', false)
            ->assertSee('rel="canonical"', false)
            ->assertDontSee('Open Checkout')
            ->assertDontSee('Secure Checkout');
    }

    public static function requiredArticleSlugProvider(): array
    {
        return [
            ['is-valorant-boosting-safe'],
            ['how-long-does-valorant-rank-boosting-take'],
            ['duo-vs-self-play-valorant-boosting'],
            ['best-valorant-boosting-services'],
            ['how-to-rank-up-in-valorant-fast'],
            ['valorant-placement-matches-explained'],
            ['what-affects-valorant-boosting-price'],
            ['valorant-radiant-boost-explained'],
        ];
    }

    public function test_unpublished_articles_are_not_publicly_accessible(): void
    {
        $article = BlogArticle::query()->create([
            'title' => 'Private Draft',
            'slug' => 'private-draft',
            'excerpt' => 'Private draft excerpt.',
            'intro' => 'Private draft intro.',
            'body' => str_repeat('Draft body ', 20),
            'status' => BlogArticle::STATUS_DRAFT,
            'published_at' => now(),
            'include_in_sitemap' => true,
        ]);

        $this->get(route('blog.show', ['slug' => $article->slug]))
            ->assertNotFound();
    }

    public function test_requested_seeded_blog_drafts_are_hidden_from_public_blog_pages(): void
    {
        $this->seed(BlogArticleSeeder::class);

        $drafts = [
            'best-valorant-agents-for-ranked' => 'Best VALORANT Agents for Ranked',
            'how-apex-ranked-works' => 'How Apex Ranked Works',
            'fastest-way-to-unlock-mw3-camos' => 'Fastest Way to Unlock MW3 Camos',
            'cs2-premier-ranking-explained' => 'CS2 Premier Ranking Explained',
            'diablo-4-best-xp-farms' => 'Diablo 4 Best XP Farms',
        ];

        $indexResponse = $this->get(route('blog.index'));
        $indexResponse->assertOk();

        foreach ($drafts as $slug => $title) {
            $this->assertDatabaseHas('blog_articles', [
                'slug' => $slug,
                'status' => BlogArticle::STATUS_DRAFT,
            ]);

            $this->get(route('blog.show', ['slug' => $slug]))
                ->assertNotFound();

            $indexResponse->assertDontSee($title);
        }
    }

    public function test_scheduled_articles_are_not_publicly_accessible_until_publish_time(): void
    {
        $article = BlogArticle::query()->create([
            'title' => 'Scheduled Article',
            'slug' => 'scheduled-article',
            'excerpt' => 'Scheduled excerpt.',
            'intro' => 'Scheduled intro.',
            'body' => str_repeat('Scheduled body ', 20),
            'status' => BlogArticle::STATUS_PUBLISHED,
            'published_at' => now()->addDay(),
            'include_in_sitemap' => true,
        ]);

        $this->get(route('blog.show', ['slug' => $article->slug]))
            ->assertNotFound();
    }

    public function test_article_page_renders_exact_meta_title(): void
    {
        $this->seed(BlogArticleSeeder::class);

        $article = BlogArticle::query()->where('slug', 'is-valorant-boosting-safe')->firstOrFail();

        $response = $this->get(route('blog.show', ['slug' => $article->slug]));

        $response->assertOk()
            ->assertSee('<title>Is Valorant Boosting Safe? Risks, Modes, and What to Check First | GGWP-Boost</title>', false)
            ->assertSee('name="description"', false)
            ->assertSee('name="robots"', false)
            ->assertSee('/#servicesTab')
            ->assertDontSee('See Live Pricing Inputs');
    }

    public function test_article_page_renders_metadata_placeholder_cta_and_related_posts(): void
    {
        $this->seed(BlogArticleSeeder::class);

        $article = BlogArticle::query()->where('slug', 'is-valorant-boosting-safe')->firstOrFail();

        $response = $this->get(route('blog.show', ['slug' => $article->slug]));

        $response->assertOk()
            ->assertSee('VALORANT Guides')
            ->assertSee('VALORANT')
            ->assertSee('Rank Boosting')
            ->assertSee('By GGWP-Boost Editorial Team')
            ->assertSee('Jan 12, 2026')
            ->assertSee('Explore VALORANT Boosts')
            ->assertSee('Related articles')
            ->assertSee('Valorant Radiant Boost Explained');
    }
}
