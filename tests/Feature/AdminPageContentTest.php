<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use App\Support\Cms\PageRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPageContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_admin_routes_are_protected(): void
    {
        $this->get(route('admin-pages.index'))
            ->assertRedirect(route('login'));

        $this->get(route('admin-pages.edit', ['pageKey' => 'home']))
            ->assertRedirect(route('login'));
    }

    public function test_non_admin_users_cannot_manage_policy_pages(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $this->actingAs($customer)
            ->get(route('admin-pages.index'))
            ->assertForbidden();

        $this->actingAs($customer)
            ->patch(route('admin-pages.update', ['pageKey' => 'privacy-policy']), $this->pagePayload('privacy-policy'))
            ->assertForbidden();
    }

    public function test_admin_page_edit_screen_uses_structured_fields_and_default_copy(): void
    {
        $admin = $this->makeAdmin();
        $expectedHeadline = data_get($this->pagePayload('home'), 'content.hero.headline');

        Page::query()->delete();

        $this->actingAs($admin)
            ->get(route('admin-pages.edit', ['pageKey' => 'home']))
            ->assertOk()
            ->assertSee('Hero')
            ->assertSee('name="content[hero][headline]"', false)
            ->assertSee($expectedHeadline)
            ->assertDontSee('name="content_json"', false);
    }

    public function test_admin_can_update_home_page_content_and_public_home_uses_saved_values(): void
    {
        $admin = $this->makeAdmin();
        $payload = $this->pagePayload('home');

        data_set($payload, 'meta_title', 'Updated Home Meta');
        data_set($payload, 'meta_description', 'Updated home meta description for search.');
        data_set($payload, 'canonical_url', route('home'));
        data_set($payload, 'robots', 'index,follow');
        data_set($payload, 'content.hero.headline', 'Updated home hero headline');
        data_set($payload, 'content.hero.description', 'Updated home hero description');
        data_set($payload, 'content.how_it_works.title', 'How The Process Works');

        $this->actingAs($admin)
            ->patch(route('admin-pages.update', ['pageKey' => 'home']), $payload)
            ->assertRedirect(route('admin-pages.edit', ['pageKey' => 'home']));

        $page = Page::query()->where('key', 'home')->firstOrFail();

        $this->assertSame('Updated home hero headline', data_get($page->content, 'hero.headline'));

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Updated home hero headline')
            ->assertSee('Updated home hero description')
            ->assertSee('How The Process Works')
            ->assertSee('<title>Updated Home Meta | GGWP-Boost</title>', false);
    }

    public function test_existing_page_record_values_load_back_into_admin_form(): void
    {
        $admin = $this->makeAdmin();
        $payload = $this->pagePayload('contact');

        data_set($payload, 'content.info.title', 'Custom Support Card');

        Page::query()->create([
            'key' => 'contact',
            'meta_title' => $payload['meta_title'],
            'meta_description' => $payload['meta_description'],
            'canonical_url' => $payload['canonical_url'],
            'robots' => $payload['robots'],
            'include_in_sitemap' => true,
            'content' => $payload['content'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin-pages.edit', ['pageKey' => 'contact']))
            ->assertOk()
            ->assertSee('Custom Support Card');
    }

    public function test_admin_page_cta_urls_must_point_to_public_destinations(): void
    {
        $admin = $this->makeAdmin();
        $payload = $this->pagePayload('home');

        data_set($payload, 'content.hero.primary_cta_url', '/definitely-missing-page');

        $this->actingAs($admin)
            ->from(route('admin-pages.edit', ['pageKey' => 'home']))
            ->patch(route('admin-pages.update', ['pageKey' => 'home']), $payload)
            ->assertRedirect(route('admin-pages.edit', ['pageKey' => 'home']))
            ->assertSessionHasErrors('content.hero.primary_cta_url');
    }

    public function test_sitemap_respects_page_sitemap_and_robot_controls(): void
    {
        $contactPayload = $this->pagePayload('contact');
        $reviewsPayload = $this->pagePayload('reviews');

        Page::query()->updateOrCreate(
            ['key' => 'contact'],
            [
                'meta_title' => $contactPayload['meta_title'],
                'meta_description' => $contactPayload['meta_description'],
                'canonical_url' => $contactPayload['canonical_url'],
                'robots' => 'noindex,follow',
                'include_in_sitemap' => true,
                'content' => $contactPayload['content'],
            ]
        );

        Page::query()->updateOrCreate(
            ['key' => 'reviews'],
            [
                'meta_title' => $reviewsPayload['meta_title'],
                'meta_description' => $reviewsPayload['meta_description'],
                'canonical_url' => $reviewsPayload['canonical_url'],
                'robots' => null,
                'include_in_sitemap' => false,
                'content' => $reviewsPayload['content'],
            ]
        );

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertDontSee(route('contact'), false)
            ->assertDontSee(route('reviews'), false)
            ->assertSee(route('home'), false);
    }

    protected function makeAdmin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'account_status' => 'active',
        ]);
    }

    protected function pagePayload(string $key): array
    {
        /** @var PageRegistry $pageRegistry */
        $pageRegistry = app(PageRegistry::class);
        $definition = $pageRegistry->page($key);

        return [
            'meta_title' => $definition['seo']['title'] ?? null,
            'meta_description' => $definition['seo']['description'] ?? null,
            'canonical_url' => $definition['seo']['canonical'] ?? null,
            'robots' => null,
            'include_in_sitemap' => true,
            'content' => $definition['content'],
        ];
    }
}
