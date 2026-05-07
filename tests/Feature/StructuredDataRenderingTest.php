<?php

namespace Tests\Feature;

use Database\Seeders\BlogArticleSeeder;
use Database\Seeders\FaqSeeder;
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
