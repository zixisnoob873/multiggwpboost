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
}
