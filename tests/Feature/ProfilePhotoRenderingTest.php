<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ProfilePhotoRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_profile_photo_renders_from_the_laravel_route_when_public_storage_is_not_linked(): void
    {
        Storage::fake('private');

        $user = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
            'first_name' => 'Casey',
            'last_name' => 'Player',
            'nickname' => 'CaseyP',
            'nickname_normalized' => 'caseyp',
        ]);

        $this->actingAs($user)
            ->post(route('user.profile-photo.update'), [
                'profile_photo' => UploadedFile::fake()->image('avatar.png', 240, 240),
            ])
            ->assertRedirect(route('customer-dashboard'));

        $user->refresh();
        $photoUrl = $user->profile_photo_url;

        $this->assertNotNull($photoUrl);

        $this->actingAs($user)
            ->get(route('customer-dashboard'))
            ->assertOk()
            ->assertSee('profile-photos/'.$user->id, false)
            ->assertSee('v='.sha1((string) $user->profile_photo_path), false)
            ->assertDontSee('/storage/'.ltrim((string) $user->profile_photo_path, '/'), false);

        $this->get($photoUrl)->assertOk();
    }

    public function test_uploaded_profile_photo_requires_a_valid_signature(): void
    {
        Storage::fake('private');

        $user = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
            'profile_photo_path' => 'uploads/profile-photos/1/avatar.png',
        ]);

        Storage::disk('private')->put($user->profile_photo_path, UploadedFile::fake()->image('avatar.png')->getContent());

        $this->get(route('profile-photos.show', ['user' => $user]))
            ->assertForbidden();

        $expiredUrl = URL::temporarySignedRoute('profile-photos.show', now()->subMinute(), [
            'user' => $user,
            'v' => sha1((string) $user->profile_photo_path),
        ]);

        $this->get($expiredUrl)
            ->assertForbidden();

        $staleUrl = URL::temporarySignedRoute('profile-photos.show', now()->addMinutes(30), [
            'user' => $user,
            'v' => sha1('uploads/profile-photos/1/old-avatar.png'),
        ]);

        $this->get($staleUrl)
            ->assertNotFound();

        $this->get($user->profile_photo_url)
            ->assertOk();
    }

    public function test_missing_profile_photo_file_falls_back_to_initials_on_customer_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
            'first_name' => 'Casey',
            'last_name' => 'Player',
            'nickname' => 'CaseyP',
            'nickname_normalized' => 'caseyp',
            'profile_photo_path' => 'uploads/profile-photos/999/missing-photo.png',
        ]);

        $this->actingAs($user)
            ->get(route('customer-dashboard'))
            ->assertOk()
            ->assertDontSee('/storage/profile-photos/999/missing-photo.png', false)
            ->assertSee('CaseyP');
    }
}
