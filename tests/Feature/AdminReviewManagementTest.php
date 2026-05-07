<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReviewManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_admin_routes_require_admin_access(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $this->get(route('admin-reviews.index'))
            ->assertRedirect(route('login'));

        $this->actingAs($customer)
            ->get(route('admin-reviews.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_update_and_delete_reviews(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('admin-reviews.store'), [
                'author_name' => 'Customer One',
                'service' => 'Rank Boosting',
                'quote' => 'This boost was smooth, fast, and communicated clearly from start to finish.',
                'sort_order' => 10,
            ])
            ->assertRedirect(route('admin-reviews.index'));

        $review = Review::query()->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin-reviews.update', ['review' => $review]), [
                'author_name' => 'Customer One',
                'service' => 'Radiant Boost',
                'quote' => 'Updated review copy that still stays long enough to satisfy the validation rules.',
                'sort_order' => 15,
            ])
            ->assertRedirect(route('admin-reviews.edit', ['review' => $review]));

        $this->assertDatabaseHas('testimonials', [
            'id' => $review->id,
            'service' => 'Radiant Boost',
            'sort_order' => 15,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin-reviews.destroy', ['review' => $review]))
            ->assertRedirect(route('admin-reviews.index'));

        $this->assertDatabaseMissing('testimonials', [
            'id' => $review->id,
        ]);
    }

    public function test_review_validation_is_enforced(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)
            ->from(route('admin-reviews.create'))
            ->post(route('admin-reviews.store'), [
                'author_name' => '',
                'service' => '',
                'quote' => 'Too short',
                'sort_order' => -1,
            ]);

        $response->assertRedirect(route('admin-reviews.create'));
        $response->assertSessionHasErrors(['author_name', 'service', 'quote', 'sort_order']);
    }

    public function test_public_reviews_page_renders_admin_managed_reviews(): void
    {
        Review::query()->create([
            'author_name' => 'Customer Prime',
            'service' => 'Rank Boosting',
            'quote' => 'Public review copy that proves the page is reading from the persisted admin-managed records.',
            'sort_order' => 1,
        ]);

        $this->get(route('reviews'))
            ->assertOk()
            ->assertSee('Customer Prime')
            ->assertSee('Public review copy that proves the page is reading from the persisted admin-managed records.');
    }

    protected function makeAdmin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'account_status' => 'active',
        ]);
    }
}
