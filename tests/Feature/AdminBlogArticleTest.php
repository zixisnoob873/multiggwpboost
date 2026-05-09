<?php

namespace Tests\Feature;

use App\Models\BlogArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBlogArticleTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_article_admin_routes_are_protected(): void
    {
        $this->get(route('admin-blog-articles.index'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_create_and_edit_a_blog_article(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);

        $payload = [
            'title' => 'Admin Created Article',
            'slug' => 'admin-created-article',
            'category_name' => 'Rank Strategy',
            'category_slug' => '',
            'tags_input' => 'VALORANT, Ranked Tips, MW3',
            'author_name' => 'GGWP-Boost Editorial Team',
            'featured_image_url' => '/images/blog/admin-created-article.jpg',
            'featured_image_alt' => 'Ranked strategy dashboard for a blog article',
            'excerpt' => 'A useful excerpt for the admin-created article.',
            'intro' => 'A useful intro for the admin-created article.',
            'body_sections' => [
                ['heading' => 'Section One', 'body' => str_repeat('A longer markdown body line. ', 10)],
                ['heading' => 'Section Two', 'body' => str_repeat('Another markdown body line. ', 10)],
            ],
            'status' => BlogArticle::STATUS_DRAFT,
            'include_in_sitemap' => '1',
            'faq_items' => [
                ['question' => 'Question one?', 'answer' => 'Answer one.'],
            ],
            'cta_label' => 'Explore Services',
            'cta_url' => '/#servicesTab',
            'meta_title' => 'Custom Meta Title',
            'meta_description' => 'Custom meta description for search engines.',
            'robots' => 'index,follow',
        ];

        $this->actingAs($admin)
            ->post(route('admin-blog-articles.store'), $payload)
            ->assertRedirect();

        $article = BlogArticle::query()->where('slug', 'admin-created-article')->firstOrFail();

        $this->assertSame('Admin Created Article', $article->title);
        $this->assertSame('Rank Strategy', $article->category_name);
        $this->assertSame('rank-strategy', $article->category_slug);
        $this->assertSame(['valorant', 'ranked-tips', 'mw3'], $article->tags);
        $this->assertSame('GGWP-Boost Editorial Team', $article->author_name);
        $this->assertSame('/images/blog/admin-created-article.jpg', $article->featured_image_url);
        $this->assertSame('Ranked strategy dashboard for a blog article', $article->featured_image_alt);
        $this->assertSame('Question one?', $article->faqItems()[0]['question']);
        $this->assertStringContainsString('## Section One', $article->body);

        $this->actingAs($admin)
            ->patch(route('admin-blog-articles.update', $article), [
                ...$payload,
                'title' => 'Admin Updated Article',
                'slug' => 'admin-updated-article',
                'category_name' => 'Updated Strategy',
                'category_slug' => 'custom-strategy',
                'tags_input' => 'CS2, Premier, Ranked',
                'author_name' => 'Editorial Desk',
                'featured_image_url' => 'https://example.com/blog/admin-updated-article.jpg',
                'featured_image_alt' => 'CS2 Premier article header',
                'status' => BlogArticle::STATUS_PUBLISHED,
                'published_at' => '2026-04-01T10:15',
                'body_sections' => [
                    ['heading' => 'Updated Section', 'body' => str_repeat('Updated body line. ', 10)],
                ],
                'faq_items' => [
                    ['question' => 'Updated question?', 'answer' => 'Updated answer.'],
                ],
            ])
            ->assertRedirect(route('admin-blog-articles.edit', $article));

        $article->refresh();

        $this->assertSame('Admin Updated Article', $article->title);
        $this->assertSame('admin-updated-article', $article->slug);
        $this->assertSame('Updated Strategy', $article->category_name);
        $this->assertSame('custom-strategy', $article->category_slug);
        $this->assertSame(['cs2', 'premier', 'ranked'], $article->tags);
        $this->assertSame('Editorial Desk', $article->author_name);
        $this->assertSame('https://example.com/blog/admin-updated-article.jpg', $article->featured_image_url);
        $this->assertSame('CS2 Premier article header', $article->featured_image_alt);
        $this->assertSame(BlogArticle::STATUS_PUBLISHED, $article->status);
        $this->assertSame('Updated question?', $article->faqItems()[0]['question']);
        $this->assertStringContainsString('## Updated Section', $article->body);
    }

    public function test_blog_article_index_moves_publish_controls_to_the_edit_screen(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);

        $draftArticle = BlogArticle::query()->create([
            'title' => 'Draft Article',
            'slug' => 'draft-article',
            'excerpt' => 'Draft excerpt.',
            'intro' => 'Draft intro.',
            'body' => "## Draft Section\n\n".str_repeat('Draft body ', 20),
            'status' => BlogArticle::STATUS_DRAFT,
            'include_in_sitemap' => true,
        ]);
        $publishedArticle = BlogArticle::query()->create([
            'title' => 'Published Article',
            'slug' => 'published-article',
            'excerpt' => 'Published excerpt.',
            'intro' => 'Published intro.',
            'body' => "## Published Section\n\n".str_repeat('Published body ', 20),
            'status' => BlogArticle::STATUS_PUBLISHED,
            'published_at' => now(),
            'include_in_sitemap' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin-blog-articles.index'))
            ->assertOk()
            ->assertDontSee(route('admin-blog-articles.publish', $draftArticle), false)
            ->assertDontSee(route('admin-blog-articles.unpublish', $publishedArticle), false);

        $this->actingAs($admin)
            ->get(route('admin-blog-articles.edit', $draftArticle))
            ->assertOk()
            ->assertSee(route('admin-blog-articles.publish', $draftArticle), false);

        $this->actingAs($admin)
            ->get(route('admin-blog-articles.edit', $publishedArticle))
            ->assertOk()
            ->assertSee(route('admin-blog-articles.unpublish', $publishedArticle), false);
    }

    public function test_slug_uniqueness_is_enforced_for_blog_articles(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);

        BlogArticle::query()->create([
            'title' => 'Existing',
            'slug' => 'existing-slug',
            'excerpt' => 'Existing excerpt.',
            'intro' => 'Existing intro.',
            'body' => "## Existing Section\n\n".str_repeat('Existing body ', 20),
            'status' => BlogArticle::STATUS_DRAFT,
            'include_in_sitemap' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('admin-blog-articles.create'))
            ->post(route('admin-blog-articles.store'), [
                'title' => 'Second',
                'slug' => 'existing-slug',
                'excerpt' => 'Second excerpt.',
                'intro' => 'Second intro.',
                'body_sections' => [
                    ['heading' => 'Section Title', 'body' => str_repeat('Second body ', 20)],
                ],
                'status' => BlogArticle::STATUS_DRAFT,
                'include_in_sitemap' => '1',
            ])
            ->assertRedirect(route('admin-blog-articles.create'))
            ->assertSessionHasErrors('slug');
    }

    public function test_validation_errors_render_for_invalid_blog_article_input(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);

        $this->actingAs($admin)
            ->from(route('admin-blog-articles.create'))
            ->post(route('admin-blog-articles.store'), [
                'title' => '',
                'slug' => '',
                'excerpt' => '',
                'intro' => '',
                'featured_image_url' => 'images/blog/missing-leading-slash.jpg',
                'body_sections' => [
                    ['heading' => 'Short Section', 'body' => 'Too short'],
                ],
                'status' => 'draft',
            ])
            ->assertRedirect(route('admin-blog-articles.create'))
            ->assertSessionHasErrors([
                'title',
                'slug',
                'excerpt',
                'intro',
                'featured_image_url',
                'featured_image_alt',
                'body_sections.0.body',
            ]);
    }

    public function test_blog_article_cta_must_point_to_a_public_non_checkout_destination(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);

        $payload = [
            'title' => 'Broken CTA Article',
            'slug' => 'broken-cta-article',
            'excerpt' => 'A useful excerpt for a broken CTA article.',
            'intro' => 'A useful intro for a broken CTA article.',
            'body_sections' => [
                ['heading' => 'Section One', 'body' => str_repeat('A longer markdown body line. ', 10)],
            ],
            'status' => BlogArticle::STATUS_DRAFT,
            'include_in_sitemap' => '1',
            'cta_label' => 'Broken CTA',
        ];

        $this->actingAs($admin)
            ->from(route('admin-blog-articles.create'))
            ->post(route('admin-blog-articles.store'), [
                ...$payload,
                'cta_url' => '/definitely-missing-page',
            ])
            ->assertRedirect(route('admin-blog-articles.create'))
            ->assertSessionHasErrors('cta_url');

        $this->actingAs($admin)
            ->from(route('admin-blog-articles.create'))
            ->post(route('admin-blog-articles.store'), [
                ...$payload,
                'cta_url' => '/checkout',
            ])
            ->assertRedirect(route('admin-blog-articles.create'))
            ->assertSessionHasErrors('cta_url');
    }

    public function test_publish_and_unpublish_actions_toggle_visibility_state(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);

        $article = BlogArticle::query()->create([
            'title' => 'Publish Test',
            'slug' => 'publish-test',
            'excerpt' => 'Publish test excerpt.',
            'intro' => 'Publish test intro.',
            'body' => "## Publish Section\n\n".str_repeat('Publish test body ', 20),
            'status' => BlogArticle::STATUS_DRAFT,
            'include_in_sitemap' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin-blog-articles.publish', $article))
            ->assertRedirect(route('admin-blog-articles.edit', $article));

        $article->refresh();

        $this->assertSame(BlogArticle::STATUS_PUBLISHED, $article->status);
        $this->assertNotNull($article->published_at);

        $this->actingAs($admin)
            ->patch(route('admin-blog-articles.unpublish', $article))
            ->assertRedirect(route('admin-blog-articles.edit', $article));

        $article->refresh();

        $this->assertSame(BlogArticle::STATUS_DRAFT, $article->status);
    }

    public function test_admin_edit_screen_uses_section_fields_and_prefills_legacy_body_content(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);

        $article = BlogArticle::query()->create([
            'title' => 'Legacy Body Article',
            'slug' => 'legacy-body-article',
            'excerpt' => 'Legacy excerpt.',
            'intro' => 'Legacy intro.',
            'body' => "## First Legacy Section\n\nLegacy paragraph one.\n\n## Second Legacy Section\n\nLegacy paragraph two.",
            'status' => BlogArticle::STATUS_DRAFT,
            'include_in_sitemap' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin-blog-articles.edit', $article))
            ->assertOk()
            ->assertSee('Sections')
            ->assertSee('name="body_sections[0][heading]"', false)
            ->assertSee('First Legacy Section')
            ->assertDontSee('name="body"', false);
    }
}
