<?php

namespace Tests\Feature;

use App\Models\BlogArticle;
use Database\Seeders\BlogArticleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_includes_published_blog_articles_and_excludes_non_indexable_entries(): void
    {
        $this->seed(BlogArticleSeeder::class);

        BlogArticle::query()->create([
            'title' => 'Draft Article',
            'slug' => 'draft-article',
            'excerpt' => 'Draft excerpt.',
            'intro' => 'Draft intro.',
            'body' => str_repeat('Draft body ', 20),
            'status' => BlogArticle::STATUS_DRAFT,
            'published_at' => now(),
            'include_in_sitemap' => true,
        ]);

        BlogArticle::query()->create([
            'title' => 'Noindex Article',
            'slug' => 'noindex-article',
            'excerpt' => 'Noindex excerpt.',
            'intro' => 'Noindex intro.',
            'body' => str_repeat('Noindex body ', 20),
            'status' => BlogArticle::STATUS_PUBLISHED,
            'published_at' => now(),
            'include_in_sitemap' => true,
            'robots' => 'noindex,follow',
        ]);

        BlogArticle::query()->create([
            'title' => 'Excluded Article',
            'slug' => 'excluded-article',
            'excerpt' => 'Excluded excerpt.',
            'intro' => 'Excluded intro.',
            'body' => str_repeat('Excluded body ', 20),
            'status' => BlogArticle::STATUS_PUBLISHED,
            'published_at' => now(),
            'include_in_sitemap' => false,
        ]);

        BlogArticle::query()->create([
            'title' => 'Scheduled Article',
            'slug' => 'scheduled-article',
            'excerpt' => 'Scheduled excerpt.',
            'intro' => 'Scheduled intro.',
            'body' => str_repeat('Scheduled body ', 20),
            'status' => BlogArticle::STATUS_PUBLISHED,
            'published_at' => now()->addDay(),
            'include_in_sitemap' => true,
        ]);

        $response = $this->get(route('sitemap'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('home'), false)
            ->assertSee(route('blog.index'), false)
            ->assertSee(route('faq'), false)
            ->assertSee(route('contact'), false)
            ->assertSee(route('become-booster'), false)
            ->assertSee(route('reviews'), false)
            ->assertSee(route('code-of-ethics'), false)
            ->assertSee(route('privacy-policy'), false)
            ->assertSee(route('refund-policy'), false)
            ->assertSee(route('terms-and-conditions'), false)
            ->assertSee(route('checkout'), false)
            ->assertSee(route('blog.show', ['slug' => 'is-valorant-boosting-safe']), false)
            ->assertSee(route('blog.show', ['slug' => 'duo-vs-self-play-valorant-boosting']), false)
            ->assertSee('<changefreq>daily</changefreq>', false)
            ->assertSee('<priority>1.0</priority>', false)
            ->assertDontSee(route('login'), false)
            ->assertDontSee(route('signup'), false)
            ->assertDontSee(route('oauth.redirect', ['provider' => 'google']), false)
            ->assertDontSee(route('oauth.redirect', ['provider' => 'discord']), false)
            ->assertDontSee(route('oauth.complete-profile'), false)
            ->assertDontSee(route('password.request'), false)
            ->assertDontSee(route('orders.success'), false)
            ->assertDontSee(route('under-maintenance'), false)
            ->assertDontSee('draft-article')
            ->assertDontSee('noindex-article')
            ->assertDontSee('scheduled-article')
            ->assertDontSee('excluded-article');
    }
}
