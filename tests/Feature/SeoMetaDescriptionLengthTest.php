<?php

namespace Tests\Feature;

use App\Models\BlogArticle;
use Database\Seeders\BlogArticleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SeoMetaDescriptionLengthTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('publicRouteProvider')]
    public function test_public_meta_descriptions_are_no_longer_than_130_characters(string $routeName): void
    {
        $response = $this->get(route($routeName));

        $this->assertMetaDescriptionLength($response->assertOk()->getContent());
    }

    public function test_seeded_blog_article_meta_descriptions_are_no_longer_than_130_characters(): void
    {
        $this->seed(BlogArticleSeeder::class);

        BlogArticle::query()
            ->pluck('slug')
            ->each(function (string $slug): void {
                $response = $this->get(route('blog.show', ['slug' => $slug]));

                $this->assertMetaDescriptionLength($response->assertOk()->getContent());
            });
    }

    public static function publicRouteProvider(): array
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
            ['login'],
            ['signup'],
            ['become-booster'],
        ];
    }

    protected function assertMetaDescriptionLength(string $html): void
    {
        preg_match_all('/<meta\s+[^>]*(?:name|property)=["\'](?:description|og:description|twitter:description)["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches);

        $this->assertNotEmpty($matches[1], 'Expected at least one rendered meta description.');

        foreach ($matches[1] as $description) {
            $decoded = html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            $this->assertLessThanOrEqual(130, mb_strlen($decoded), $decoded);
        }
    }
}
