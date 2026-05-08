<?php

namespace Tests\Feature;

use App\Models\BlogArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HomeLatestBlogsSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_shows_latest_six_published_blog_articles_in_rotating_groups(): void
    {
        foreach (range(1, 7) as $index) {
            BlogArticle::query()->create([
                'title' => 'Blog '.$index,
                'slug' => 'blog-'.$index,
                'excerpt' => 'Excerpt '.$index,
                'intro' => 'Intro '.$index,
                'body' => str_repeat('Body '.$index.' ', 20),
                'status' => BlogArticle::STATUS_PUBLISHED,
                'published_at' => now()->subDays(8 - $index),
                'include_in_sitemap' => true,
            ]);
        }

        BlogArticle::query()->create([
            'title' => 'Draft Blog',
            'slug' => 'draft-blog',
            'excerpt' => 'Draft excerpt',
            'intro' => 'Draft intro',
            'body' => str_repeat('Draft body ', 20),
            'status' => BlogArticle::STATUS_DRAFT,
            'published_at' => now()->addDay(),
            'include_in_sitemap' => true,
        ]);

        Cache::flush();

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('Game Boosting Guides')
            ->assertSee('Blog 7')
            ->assertSee('Blog 6')
            ->assertSee('Blog 5')
            ->assertSee('Blog 4')
            ->assertSee('Blog 3')
            ->assertSee('Blog 2')
            ->assertDontSee('Blog 1')
            ->assertDontSee('Draft Blog');

        $html = $response->getContent();

        $this->assertSame(4, substr_count($html, 'Show latest blog set'));
    }

    public function test_home_page_latest_blogs_section_degrades_gracefully_with_fewer_than_three_articles(): void
    {
        foreach (range(1, 2) as $index) {
            BlogArticle::query()->create([
                'title' => 'Short Blog '.$index,
                'slug' => 'short-blog-'.$index,
                'excerpt' => 'Excerpt '.$index,
                'intro' => 'Intro '.$index,
                'body' => str_repeat('Body '.$index.' ', 20),
                'status' => BlogArticle::STATUS_PUBLISHED,
                'published_at' => now()->subDays(3 - $index),
                'include_in_sitemap' => true,
            ]);
        }

        Cache::flush();

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('Game Boosting Guides')
            ->assertSee('Short Blog 1')
            ->assertSee('Short Blog 2');

        $html = $response->getContent();

        $this->assertSame(0, substr_count($html, 'Show latest blog set'));
    }
}
