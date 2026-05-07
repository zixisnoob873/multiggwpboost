<?php

namespace Tests\Feature;

use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PromotionImageSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unsigned_promotion_image_route_returns_forbidden(): void
    {
        $promotion = Promotion::factory()->create([
            'image_path' => 'uploads/promotion-images/banner.jpg',
        ]);

        $this->get(route('promotion-images.show', ['promotion' => $promotion]))
            ->assertForbidden();
    }

    public function test_expired_signed_promotion_image_url_returns_forbidden(): void
    {
        $promotion = Promotion::factory()->create([
            'image_path' => 'uploads/promotion-images/banner.jpg',
        ]);

        $url = URL::temporarySignedRoute('promotion-images.show', now()->subMinute(), [
            'promotion' => $promotion,
            'v' => sha1((string) $promotion->image_path),
        ]);

        $this->get($url)->assertForbidden();
    }

    public function test_tampered_promotion_image_version_hash_returns_not_found(): void
    {
        Storage::fake('private');

        $promotion = Promotion::factory()->create([
            'image_path' => 'uploads/promotion-images/banner.jpg',
        ]);
        Storage::disk('private')->put($promotion->image_path, 'image');

        $url = URL::temporarySignedRoute('promotion-images.show', now()->addMinutes(30), [
            'promotion' => $promotion,
            'v' => sha1('uploads/promotion-images/old-banner.jpg'),
        ]);

        $this->get($url)->assertNotFound();
    }

    public function test_unsafe_promotion_image_paths_return_not_found(): void
    {
        foreach (['../.env', '/etc/passwd', 'http://example.com/x.jpg', 'uploads/promotion-images/../../.env'] as $path) {
            $promotion = Promotion::factory()->create([
                'image_path' => $path,
            ]);

            $url = URL::temporarySignedRoute('promotion-images.show', now()->addMinutes(30), [
                'promotion' => $promotion,
                'v' => sha1($path),
            ]);

            $this->get($url)->assertNotFound();
        }
    }

    public function test_active_promotion_valid_signed_url_serves_image(): void
    {
        Storage::fake('private');

        $promotion = Promotion::factory()->create([
            'is_active' => true,
            'image_path' => 'uploads/promotion-images/banner.jpg',
        ]);
        Storage::disk('private')->put($promotion->image_path, 'image');

        $url = URL::temporarySignedRoute('promotion-images.show', now()->addMinutes(30), [
            'promotion' => $promotion,
            'v' => sha1((string) $promotion->image_path),
        ]);

        $this->get($url)->assertOk();
    }

    public function test_inactive_promotion_signed_url_is_hidden_publicly_but_visible_to_admin(): void
    {
        Storage::fake('private');

        $promotion = Promotion::factory()->inactive()->create([
            'image_path' => 'uploads/promotion-images/inactive.jpg',
        ]);
        Storage::disk('private')->put($promotion->image_path, 'image');

        $url = URL::temporarySignedRoute('promotion-images.show', now()->addMinutes(30), [
            'promotion' => $promotion,
            'v' => sha1((string) $promotion->image_path),
        ]);

        $this->get($url)->assertNotFound();

        $admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'account_status' => 'active',
        ]);

        $this->actingAs($admin)->get($url)->assertOk();
    }
}
