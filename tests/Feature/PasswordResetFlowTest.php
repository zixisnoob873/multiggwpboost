<?php

namespace Tests\Feature;

use App\Mail\Transactional\PasswordResetMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_accounts_receive_a_generic_password_reset_response_and_email(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $response
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', 'If an account with that email exists, we have emailed a password reset link.');

        Mail::assertQueued(PasswordResetMail::class, function (PasswordResetMail $mail) use ($user) {
            return data_get($mail->payload, 'user.email') === $user->email
                && str_contains((string) data_get($mail->payload, 'reset.url'), 'reset-password')
                && str_contains((string) data_get($mail->payload, 'reset.url'), urlencode($user->email));
        });
    }

    public function test_missing_accounts_still_receive_the_generic_password_reset_response(): void
    {
        Mail::fake();

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => 'missing-user@example.com',
        ]);

        $response
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', 'If an account with that email exists, we have emailed a password reset link.');

        Mail::assertNothingQueued();
    }

    public function test_password_reset_requests_are_rate_limited_to_three_attempts_per_hour(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->from(route('password.request'))
                ->post(route('password.email'), [
                    'email' => $user->email,
                ])
                ->assertRedirect(route('password.request'));
        }

        $this->post(route('password.email'), [
            'email' => $user->email,
        ])->assertStatus(429);
    }

    public function test_expired_reset_tokens_are_rejected(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $token = Password::broker()->createToken($user);

        DB::table(config('auth.passwords.users.table'))
            ->where('email', $user->email)
            ->update(['created_at' => now()->subHours(2)]);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertRedirect(route('password.request'));

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewSecurePass123!',
            'password_confirmation' => 'NewSecurePass123!',
        ])->assertRedirect(route('password.request'));
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
            'password' => 'OldSecurePass123!',
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewSecurePass123!',
            'password_confirmation' => 'NewSecurePass123!',
        ]);

        $response->assertRedirect(route('customer-dashboard'));
        $this->assertAuthenticatedAs($user->fresh());
        $this->assertTrue(Hash::check('NewSecurePass123!', $user->fresh()->password));
    }
}
