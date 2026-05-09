<?php

namespace Tests\Feature;

use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPromotionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_promotion_admin_routes_are_protected(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $this->get(route('admin-promotions.index'))
            ->assertRedirect(route('login'));

        $this->actingAs($customer)
            ->get(route('admin-promotions.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_a_promotion_with_a_private_image(): void
    {
        Storage::fake('private');

        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('admin-promotions.store'), [
            'title' => 'Weekend Rank Rush',
            'description' => 'Limited slots for faster turnaround this week.',
            'button_text' => 'Start Boost',
            'button_link' => '/#servicesTab',
            'is_active' => '1',
            'show_on_homepage' => '1',
            'sort_order' => 2,
            'image' => UploadedFile::fake()->image('rank-rush.png', 1600, 900),
        ]);

        $response
            ->assertRedirect(route('admin-promotions.index'))
            ->assertSessionHas('status');

        $promotion = Promotion::query()->where('title', 'Weekend Rank Rush')->firstOrFail();

        $this->assertStringStartsWith('uploads/promotion-images/', $promotion->image_path);
        Storage::disk('private')->assertExists($promotion->image_path);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Weekend Rank Rush')
            ->assertSee('Limited slots for faster turnaround this week.')
            ->assertSee('Start Boost')
            ->assertSee('promotion-images/'.$promotion->id, false)
            ->assertDontSee('/storage/'.$promotion->image_path, false);
    }

    public function test_promotion_store_auto_assigns_the_next_sort_order_when_left_blank(): void
    {
        Storage::fake('private');

        $admin = $this->makeAdmin();

        Promotion::factory()->create(['sort_order' => 1]);
        Promotion::factory()->create(['sort_order' => 4]);

        $this->actingAs($admin)->post(route('admin-promotions.store'), [
            'title' => 'Auto Ordered Promotion',
            'description' => 'Automatically picks the next homepage order.',
            'button_text' => 'Start',
            'button_link' => '/#servicesTab',
            'is_active' => '1',
            'show_on_homepage' => '1',
            'sort_order' => '',
            'image' => UploadedFile::fake()->image('auto-order.png', 1600, 900),
        ])->assertRedirect(route('admin-promotions.index'));

        $this->assertDatabaseHas('promotions', [
            'title' => 'Auto Ordered Promotion',
            'sort_order' => 5,
        ]);
    }

    public function test_promotion_button_link_must_point_to_a_public_destination(): void
    {
        Storage::fake('private');

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->from(route('admin-promotions.index'))
            ->post(route('admin-promotions.store'), [
                'title' => 'Broken CTA',
                'description' => 'This CTA should not be accepted.',
                'button_text' => 'Broken',
                'button_link' => '/definitely-missing-page',
                'is_active' => '1',
                'show_on_homepage' => '1',
                'sort_order' => 2,
                'image' => UploadedFile::fake()->image('broken.png', 1600, 900),
            ])
            ->assertRedirect(route('admin-promotions.index'))
            ->assertSessionHasErrors('button_link');

        $this->actingAs($admin)
            ->from(route('admin-promotions.index'))
            ->post(route('admin-promotions.store'), [
                'title' => 'Private CTA',
                'description' => 'This CTA should not point to admin.',
                'button_text' => 'Private',
                'button_link' => '/admin/dashboard',
                'is_active' => '1',
                'show_on_homepage' => '1',
                'sort_order' => 3,
                'image' => UploadedFile::fake()->image('private.png', 1600, 900),
            ])
            ->assertRedirect(route('admin-promotions.index'))
            ->assertSessionHasErrors('button_link');
    }

    public function test_homepage_only_renders_active_homepage_promotions_in_sort_order(): void
    {
        Promotion::factory()->create([
            'title' => 'Third Visible',
            'description' => 'Visible after the first card.',
            'sort_order' => 3,
        ]);
        Promotion::factory()->create([
            'title' => 'First Visible',
            'description' => 'Visible first on the homepage.',
            'sort_order' => 0,
        ]);
        Promotion::factory()->inactive()->create([
            'title' => 'Inactive Hidden',
            'description' => 'This should not appear.',
            'sort_order' => 1,
        ]);
        Promotion::factory()->hiddenFromHomepage()->create([
            'title' => 'Homepage Hidden',
            'description' => 'This should also stay hidden.',
            'sort_order' => 2,
        ]);

        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('First Visible')
            ->assertSee('Third Visible')
            ->assertDontSee('Inactive Hidden')
            ->assertDontSee('Homepage Hidden');

        $html = $response->getContent();

        $this->assertNotFalse(strpos($html, 'First Visible'));
        $this->assertNotFalse(strpos($html, 'Third Visible'));
        $this->assertLessThan(
            strpos($html, 'Third Visible'),
            strpos($html, 'First Visible')
        );
    }

    public function test_homepage_promotion_section_gracefully_handles_an_empty_collection(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Premium Game Boosting Services for Every Competitive Title')
            ->assertSee('Choose your game')
            ->assertDontSee('ggwp-home-promotions', false);
    }

    public function test_updating_a_promotion_replaces_the_old_image_and_deletes_the_old_file(): void
    {
        Storage::fake('private');

        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin-promotions.store'), [
            'title' => 'Replace Me',
            'description' => 'Original image copy.',
            'button_text' => 'Learn More',
            'button_link' => '/checkout',
            'is_active' => '1',
            'show_on_homepage' => '1',
            'sort_order' => 1,
            'image' => UploadedFile::fake()->image('original.png', 1600, 900),
        ]);

        $promotion = Promotion::query()->where('title', 'Replace Me')->firstOrFail();
        $oldImagePath = $promotion->image_path;

        Storage::disk('private')->assertExists($oldImagePath);

        $response = $this->actingAs($admin)->patch(route('admin-promotions.update', $promotion), [
            'title' => 'Replace Me',
            'description' => 'Updated image copy.',
            'button_text' => 'Go Now',
            'button_link' => '/#servicesTab',
            'is_active' => '1',
            'show_on_homepage' => '1',
            'sort_order' => 4,
            'image' => UploadedFile::fake()->image('replacement.webp', 1600, 900),
        ]);

        $response
            ->assertRedirect(route('admin-promotions.edit', $promotion))
            ->assertSessionHas('status');

        $promotion->refresh();

        $this->assertNotSame($oldImagePath, $promotion->image_path);
        Storage::disk('private')->assertMissing($oldImagePath);
        Storage::disk('private')->assertExists($promotion->image_path);
    }

    public function test_promotion_actions_are_only_rendered_on_the_edit_screen(): void
    {
        $admin = $this->makeAdmin();
        $promotion = Promotion::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin-promotions.index'))
            ->assertOk()
            ->assertDontSee(route('admin-promotions.toggle-active', $promotion), false)
            ->assertDontSee(route('admin-promotions.toggle-homepage', $promotion), false)
            ->assertDontSee('action="'.route('admin-promotions.destroy', $promotion).'"', false);

        $this->actingAs($admin)
            ->get(route('admin-promotions.edit', $promotion))
            ->assertOk()
            ->assertSee(route('admin-promotions.toggle-active', $promotion), false)
            ->assertSee(route('admin-promotions.toggle-homepage', $promotion), false)
            ->assertSee('action="'.route('admin-promotions.destroy', $promotion).'"', false);
    }

    public function test_admin_can_toggle_status_update_sort_order_from_edit_flow_and_delete_promotions_without_removing_shared_images_too_early(): void
    {
        Storage::fake('public');
        Storage::fake('private');

        $admin = $this->makeAdmin();
        $sharedPath = 'promotion_pics/shared-banner.jpg';

        Storage::disk('public')->put($sharedPath, 'shared image');

        $firstPromotion = Promotion::factory()->create([
            'title' => 'Shared Banner A',
            'description' => 'Shared description A.',
            'button_text' => 'Start',
            'button_link' => '/#servicesTab',
            'image_path' => $sharedPath,
            'is_active' => true,
            'show_on_homepage' => true,
            'sort_order' => 1,
        ]);
        $secondPromotion = Promotion::factory()->create([
            'title' => 'Shared Banner B',
            'image_path' => $sharedPath,
            'is_active' => true,
            'show_on_homepage' => true,
            'sort_order' => 2,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin-promotions.toggle-active', $firstPromotion))
            ->assertRedirect(route('admin-promotions.edit', $firstPromotion));

        $this->actingAs($admin)
            ->patch(route('admin-promotions.toggle-homepage', $firstPromotion))
            ->assertRedirect(route('admin-promotions.edit', $firstPromotion));

        $this->actingAs($admin)
            ->patch(route('admin-promotions.update', $firstPromotion), [
                'title' => $firstPromotion->title,
                'description' => $firstPromotion->description,
                'button_text' => $firstPromotion->button_text,
                'button_link' => $firstPromotion->button_link,
                'sort_order' => 7,
            ])
            ->assertRedirect(route('admin-promotions.edit', $firstPromotion));

        $firstPromotion->refresh();

        $this->assertFalse($firstPromotion->is_active);
        $this->assertFalse($firstPromotion->show_on_homepage);
        $this->assertSame(7, $firstPromotion->sort_order);

        $this->actingAs($admin)
            ->delete(route('admin-promotions.destroy', $firstPromotion))
            ->assertRedirect(route('admin-promotions.index'));

        Storage::disk('public')->assertExists($sharedPath);

        $this->actingAs($admin)
            ->delete(route('admin-promotions.destroy', $secondPromotion))
            ->assertRedirect(route('admin-promotions.index'));

        Storage::disk('public')->assertMissing($sharedPath);
        $this->assertDatabaseMissing('promotions', ['id' => $firstPromotion->id]);
        $this->assertDatabaseMissing('promotions', ['id' => $secondPromotion->id]);
    }

    protected function makeAdmin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'account_status' => 'active',
        ]);
    }
}
