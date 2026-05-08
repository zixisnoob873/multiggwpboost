<?php

namespace Tests\Feature;

use App\Models\BlogArticle;
use Database\Seeders\BlogArticleSeeder;
use Database\Seeders\FaqSeeder;
use Database\Seeders\GameCatalogSeeder;
use Database\Seeders\ReviewSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StructuredDataRenderingTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('importantPublicPageProvider')]
    public function test_important_public_pages_render_a_context_rich_json_ld_graph(string $routeName): void
    {
        $this->seed([FaqSeeder::class, ReviewSeeder::class]);

        $schema = $this->structuredDataFrom(route($routeName));
        $graph = $schema['@graph'] ?? [];

        $this->assertSame('https://schema.org', $schema['@context'] ?? null);
        $this->assertNotEmpty($this->findGraphNode($graph, 'Organization'));
        $this->assertNotEmpty($this->findGraphNode($graph, 'WebSite'));

        $webPage = $this->findGraphNode($graph, 'WebPage')
            ?: $this->findGraphNode($graph, 'CollectionPage')
            ?: $this->findGraphNode($graph, 'ContactPage');

        $this->assertNotEmpty($webPage, 'Expected a WebPage-family node.');
        $this->assertNotEmpty($webPage['about'] ?? [], 'Expected the page to describe its main topic.');
        $this->assertNotEmpty($webPage['audience'] ?? [], 'Expected the page to describe who it is for.');
        $this->assertNotEmpty($webPage['significantLink'] ?? [], 'Expected the page to expose relevant links.');
        $this->assertNotEmpty($this->findGraphNode($graph, 'BreadcrumbList'));
    }

    public static function importantPublicPageProvider(): array
    {
        return [
            ['home'],
            ['blog.index'],
            ['contact'],
            ['faq'],
            ['checkout'],
            ['code-of-ethics'],
            ['privacy-policy'],
            ['refund-policy'],
            ['reviews'],
            ['terms-and-conditions'],
            ['become-booster'],
        ];
    }

    public function test_faq_page_schema_uses_visible_faq_questions(): void
    {
        $this->seed(FaqSeeder::class);

        $schema = $this->structuredDataFrom(route('faq'));
        $faqPage = $this->findGraphNode($schema['@graph'], 'FAQPage');

        $this->assertNotEmpty($faqPage);
        $this->assertSame('How long does boosting take?', data_get($faqPage, 'mainEntity.0.name'));
        $this->assertSame('Answer', data_get($faqPage, 'mainEntity.0.acceptedAnswer.@type'));
    }

    public function test_blog_article_schema_identifies_the_article_faq_and_related_entities(): void
    {
        $this->seed(BlogArticleSeeder::class);

        $schema = $this->structuredDataFrom(route('blog.show', ['slug' => 'is-valorant-boosting-safe']));
        $graph = $schema['@graph'];

        $article = $this->findGraphNode($graph, 'BlogPosting');
        $faqPage = $this->findGraphNode($graph, 'FAQPage');
        $webPage = $this->findGraphNode($graph, 'WebPage');

        $this->assertSame('Is Valorant Boosting Safe? The Real Risks, Trade-Offs, and Smarter Options', $article['headline'] ?? null);
        $this->assertNotEmpty($article['about'] ?? []);
        $this->assertNotEmpty($article['audience'] ?? []);
        $this->assertNotEmpty($faqPage['mainEntity'] ?? []);
        $this->assertSame($article['@id'] ?? null, data_get($webPage, 'mainEntity.@id'));
    }

    public function test_blog_article_schema_includes_author_image_section_and_tag_keywords_when_available(): void
    {
        $article = BlogArticle::query()->create([
            'title' => 'Schema Metadata Article',
            'slug' => 'schema-metadata-article',
            'category_name' => 'CS2 Ranked',
            'category_slug' => 'cs2-ranked',
            'tags' => ['cs2', 'premier', 'ranked'],
            'author_name' => 'Editorial Desk',
            'featured_image_url' => '/images/blog/schema-metadata-article.jpg',
            'featured_image_alt' => 'CS2 Premier rating chart',
            'excerpt' => 'Schema metadata excerpt.',
            'intro' => 'Schema metadata intro.',
            'body' => "## Schema Section\n\n".str_repeat('Schema metadata body. ', 20),
            'status' => BlogArticle::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'include_in_sitemap' => true,
        ]);

        $schema = $this->structuredDataFrom(route('blog.show', ['slug' => $article->slug]));
        $graph = $schema['@graph'];

        $posting = $this->findGraphNode($graph, 'BlogPosting');
        $webPage = $this->findGraphNode($graph, 'WebPage');

        $this->assertSame('Editorial Desk', data_get($posting, 'author.name'));
        $this->assertSame('CS2 Ranked', $posting['articleSection'] ?? null);
        $this->assertContains('CS2', $posting['keywords'] ?? []);
        $this->assertContains('Premier', $posting['keywords'] ?? []);
        $this->assertSame($article->effectiveFeaturedImageUrl(), data_get($posting, 'image.url'));
        $this->assertSame('CS2 Premier rating chart', data_get($posting, 'image.caption'));
        $this->assertSame($article->effectiveFeaturedImageUrl(), data_get($webPage, 'primaryImageOfPage.url'));
    }

    public function test_category_and_service_pages_render_marketplace_structured_data(): void
    {
        $this->seed(GameCatalogSeeder::class);

        $categorySchema = $this->structuredDataFrom(route('games.categories.show', ['category' => 'fps']));
        $this->assertNotEmpty($this->findGraphNode($categorySchema['@graph'], 'CollectionPage'));
        $this->assertNotEmpty($this->findGraphNode($categorySchema['@graph'], 'ItemList'));
        $this->assertNotEmpty($this->findGraphNode($categorySchema['@graph'], 'BreadcrumbList'));

        $serviceSchema = $this->structuredDataFrom(route('game.services.show', [
            'game' => 'cs2',
            'service' => 'faceit-elo',
        ]));
        $service = $this->findGraphNode($serviceSchema['@graph'], 'Service');
        $breadcrumb = $this->findGraphNode($serviceSchema['@graph'], 'BreadcrumbList');

        $this->assertSame('CS2 Faceit ELO', $service['name'] ?? null);
        $this->assertSame('FPS', data_get($breadcrumb, 'itemListElement.1.name'));
    }

    protected function structuredDataFrom(string $url): array
    {
        $response = $this->get($url);
        $response->assertOk();

        preg_match('/<script\b[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $response->getContent(), $matches);

        $this->assertNotEmpty($matches[1] ?? null, 'Expected JSON-LD script.');

        $schema = json_decode($matches[1], true);

        $this->assertIsArray($schema);
        $this->assertNotEmpty($schema['@graph'] ?? []);

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
