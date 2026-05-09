<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginCaptchaFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_captcha_is_hidden_before_threshold_and_rendered_after_third_failure(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
            'password' => 'ValidPass123!',
        ]);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('data-captcha-required="1"', false);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->from(route('login'))
                ->post(route('login.submit'), [
                    'email' => $user->email,
                    'password' => 'wrong-password',
                ])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors('email');
        }

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-captcha-required="1"', false)
            ->assertSee('Enter the numeric code exactly as shown.');
    }

    public function test_login_requires_captcha_after_threshold_and_rejects_missing_or_wrong_codes(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
            'password' => 'ValidPass123!',
        ]);

        $this->reachCaptchaThreshold($user);

        $missingCaptcha = $this->from(route('login'))->post(route('login.submit'), [
            'email' => $user->email,
            'password' => 'ValidPass123!',
        ]);

        $missingCaptcha->assertRedirect(route('login'));
        $missingCaptcha->assertSessionHasErrors('captcha');
        $this->assertGuest();

        $this->get(route('login'))->assertOk();
        $challenge = (string) session('auth.login_captcha.challenge.code');
        $this->assertMatchesRegularExpression('/^\d{7}$/', $challenge);

        $wrongCaptcha = $this->from(route('login'))->post(route('login.submit'), [
            'email' => $user->email,
            'password' => 'ValidPass123!',
            'captcha' => $challenge === '1234567' ? '7654321' : '1234567',
        ]);

        $wrongCaptcha->assertRedirect(route('login'));
        $wrongCaptcha->assertSessionHasErrors('captcha');
        $this->assertGuest();
    }

    public function test_successful_login_with_captcha_resets_failed_attempt_state(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
            'password' => 'ValidPass123!',
        ]);

        $this->reachCaptchaThreshold($user);

        $this->get(route('login'))->assertOk();
        $challenge = (string) session('auth.login_captcha.challenge.code');

        $this->post(route('login.submit'), [
            'email' => $user->email,
            'password' => 'ValidPass123!',
            'captcha' => $challenge,
        ])->assertRedirect(route('customer-dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('auth.login_captcha.challenge'));
        $this->assertNull(session('auth.login_captcha.failed_attempts'));

        $this->post(route('logout'))->assertRedirect(route('home'));

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('data-captcha-required="1"', false);
    }

    public function test_login_blocks_unverified_accounts_when_verified_login_is_required(): void
    {
        config(['auth.require_verified_login' => true]);

        $user = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
            'email_verified_at' => null,
            'password' => 'ValidPass123!',
        ]);

        $response = $this->from(route('login'))->post(route('login.submit'), [
            'email' => $user->email,
            'password' => 'ValidPass123!',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Please verify your email address before logging in.', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_verified_accounts_can_log_in_when_verified_login_is_required(): void
    {
        config(['auth.require_verified_login' => true]);

        $user = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
            'email_verified_at' => now(),
            'password' => 'ValidPass123!',
        ]);

        $this->post(route('login.submit'), [
            'email' => $user->email,
            'password' => 'ValidPass123!',
        ])->assertRedirect(route('customer-dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    protected function reachCaptchaThreshold(User $user): void
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->from(route('login'))
                ->post(route('login.submit'), [
                    'email' => $user->email,
                    'password' => 'wrong-password',
                ])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors('email');
        }
    }
}
