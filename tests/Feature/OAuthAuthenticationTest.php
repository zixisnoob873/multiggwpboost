<?php

namespace Tests\Feature;

use App\Models\OAuthAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;
use Throwable;

class OAuthAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_signup_buttons_point_to_oauth_redirect_routes(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('href="'.route('oauth.redirect', ['provider' => 'google']).'"', false)
            ->assertSee('href="'.route('oauth.redirect', ['provider' => 'discord']).'"', false);

        $this->get(route('signup'))
            ->assertOk()
            ->assertSee('href="'.route('oauth.redirect', ['provider' => 'google']).'"', false)
            ->assertSee('href="'.route('oauth.redirect', ['provider' => 'discord']).'"', false);
    }

    public function test_oauth_callback_with_existing_local_email_does_not_link_or_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@example.test',
            'role' => User::ROLE_CUSTOMER,
            'account_status' => 'active',
            'email_verified_at' => null,
        ]);

        $this->fakeSocialite('google', $this->socialiteUser([
            'id' => 'google-123',
            'name' => 'Demo Customer',
            'email' => 'customer@example.test',
            'raw' => [
                'sub' => 'google-123',
                'name' => 'Demo Customer',
                'given_name' => 'Demo',
                'family_name' => 'Customer',
                'email' => 'customer@example.test',
                'email_verified' => true,
            ],
        ]));

        $this->get(route('oauth.callback', ['provider' => 'google']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'oauth' => 'An account with this email already exists. Sign in with your password first, then connect this provider from account settings.',
            ]);

        $this->assertGuest();
        $this->assertDatabaseMissing('oauth_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-123',
        ]);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_oauth_callback_with_unverified_provider_email_is_rejected(): void
    {
        $this->fakeSocialite('google', $this->socialiteUser([
            'id' => 'google-unverified',
            'name' => 'Unverified Customer',
            'email' => 'unverified@example.test',
            'raw' => [
                'sub' => 'google-unverified',
                'name' => 'Unverified Customer',
                'given_name' => 'Unverified',
                'family_name' => 'Customer',
                'email' => 'unverified@example.test',
                'email_verified' => false,
            ],
        ]));

        $this->get(route('oauth.callback', ['provider' => 'google']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('oauth');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'unverified@example.test',
        ]);
        $this->assertSame(0, OAuthAccount::query()->count());
    }

    public function test_already_linked_oauth_account_can_still_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'linked@example.test',
            'role' => User::ROLE_CUSTOMER,
            'account_status' => 'active',
        ]);

        OAuthAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-linked-login',
            'email' => 'linked@example.test',
        ]);

        $this->fakeSocialite('google', $this->socialiteUser([
            'id' => 'google-linked-login',
            'name' => 'Linked Customer',
            'email' => 'linked@example.test',
            'raw' => [
                'sub' => 'google-linked-login',
                'name' => 'Linked Customer',
                'given_name' => 'Linked',
                'family_name' => 'Customer',
                'email' => 'linked@example.test',
                'email_verified' => true,
            ],
        ]));

        $this->get(route('oauth.callback', ['provider' => 'google']))
            ->assertRedirect(route('customer-dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_new_google_user_with_missing_nickname_is_sent_to_complete_profile(): void
    {
        $this->fakeSocialite('google', $this->socialiteUser([
            'id' => 'google-456',
            'name' => 'Demo Customer',
            'email' => 'new-google@example.test',
            'raw' => [
                'sub' => 'google-456',
                'name' => 'Demo Customer',
                'given_name' => 'Demo',
                'family_name' => 'Customer',
                'email' => 'new-google@example.test',
                'email_verified' => true,
            ],
        ]));

        $this->get(route('oauth.callback', ['provider' => 'google']))
            ->assertRedirect(route('oauth.complete-profile'))
            ->assertSessionHas('oauth.pending_profile');

        $this->get(route('oauth.complete-profile'))
            ->assertOk()
            ->assertSee('value="Demo"', false)
            ->assertSee('value="Customer"', false)
            ->assertSee('value="new-google@example.test"', false)
            ->assertSee('value="DemoCustomer"', false)
            ->assertSee('readonly', false);
    }

    public function test_complete_profile_creates_customer_without_password_and_links_provider(): void
    {
        $this->withSession([
            'oauth.pending_profile' => $this->pendingProfile([
                'provider' => 'discord',
                'provider_label' => 'Discord',
                'provider_user_id' => 'discord-123',
                'email' => 'discord@example.test',
                'email_verified' => true,
                'name' => 'Demo Discord',
                'nickname' => 'DemoDiscord',
                'suggested_nickname' => 'DemoDiscord',
            ]),
        ])->post(route('oauth.complete-profile.submit'), [
            'first_name' => 'Demo',
            'last_name' => 'Customer',
            'nickname' => 'DemoDiscord',
            'email' => 'discord@example.test',
        ])->assertRedirect(route('customer-dashboard'));

        $user = User::query()->where('email', 'discord@example.test')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->password);
        $this->assertSame(User::ROLE_CUSTOMER, $user->role);
        $this->assertDatabaseHas('oauth_accounts', [
            'user_id' => $user->id,
            'provider' => 'discord',
            'provider_user_id' => 'discord-123',
        ]);
    }

    public function test_complete_profile_rejects_duplicate_nickname(): void
    {
        User::factory()->create([
            'nickname' => 'TakenName',
            'nickname_normalized' => 'takenname',
        ]);

        $this->withSession([
            'oauth.pending_profile' => $this->pendingProfile([
                'provider_user_id' => 'google-789',
                'email' => 'nickname@example.test',
                'email_verified' => true,
            ]),
        ])->from(route('oauth.complete-profile'))
            ->post(route('oauth.complete-profile.submit'), [
                'first_name' => 'Demo',
                'last_name' => 'Customer',
                'nickname' => 'TakenName',
                'email' => 'nickname@example.test',
            ])
            ->assertRedirect(route('oauth.complete-profile'))
            ->assertSessionHasErrors('nickname');
    }

    public function test_missing_provider_email_is_rejected_without_starting_profile_completion(): void
    {
        $this->fakeSocialite('discord', $this->socialiteUser([
            'id' => 'discord-no-email',
            'nickname' => 'DiscordUser',
            'name' => 'Discord User',
            'email' => null,
            'raw' => [
                'id' => 'discord-no-email',
                'username' => 'DiscordUser',
                'global_name' => 'Discord User',
                'email' => null,
                'verified' => false,
            ],
        ]));

        $this->get(route('oauth.callback', ['provider' => 'discord']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('oauth')
            ->assertSessionMissing('oauth.pending_profile');
    }

    public function test_authenticated_user_can_explicitly_link_verified_oauth_provider_after_password_confirmation(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@example.test',
            'role' => User::ROLE_CUSTOMER,
            'account_status' => 'active',
        ]);

        $this->fakeSocialite('google', $this->socialiteUser([
            'id' => 'google-explicit-link',
            'name' => 'Demo Customer',
            'email' => 'customer@example.test',
            'raw' => [
                'sub' => 'google-explicit-link',
                'name' => 'Demo Customer',
                'email' => 'customer@example.test',
                'email_verified' => true,
            ],
        ]));

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('oauth.link.callback', ['provider' => 'google']))
            ->assertRedirect(route('customer-dashboard'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('oauth_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-explicit-link',
            'email' => 'customer@example.test',
        ]);
    }

    public function test_authenticated_user_cannot_link_provider_with_a_different_email(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@example.test',
            'role' => User::ROLE_CUSTOMER,
            'account_status' => 'active',
        ]);

        $this->fakeSocialite('google', $this->socialiteUser([
            'id' => 'google-wrong-email',
            'name' => 'Wrong Email',
            'email' => 'other@example.test',
            'raw' => [
                'sub' => 'google-wrong-email',
                'name' => 'Wrong Email',
                'email' => 'other@example.test',
                'email_verified' => true,
            ],
        ]));

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('oauth.link.callback', ['provider' => 'google']))
            ->assertRedirect(route('customer-dashboard'))
            ->assertSessionHasErrors('oauth');

        $this->assertDatabaseMissing('oauth_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-wrong-email',
        ]);
    }

    public function test_linked_provider_account_with_conflicting_email_is_rejected(): void
    {
        $linkedUser = User::factory()->create([
            'email' => 'linked@example.test',
            'role' => User::ROLE_CUSTOMER,
            'account_status' => 'active',
        ]);
        User::factory()->create([
            'email' => 'other@example.test',
            'role' => User::ROLE_CUSTOMER,
            'account_status' => 'active',
        ]);
        OAuthAccount::query()->create([
            'user_id' => $linkedUser->id,
            'provider' => 'google',
            'provider_user_id' => 'google-linked',
            'email' => 'linked@example.test',
        ]);

        $this->fakeSocialite('google', $this->socialiteUser([
            'id' => 'google-linked',
            'name' => 'Other Customer',
            'email' => 'other@example.test',
            'raw' => [
                'sub' => 'google-linked',
                'name' => 'Other Customer',
                'email' => 'other@example.test',
                'email_verified' => true,
            ],
        ]));

        $this->get(route('oauth.callback', ['provider' => 'google']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('oauth');

        $this->assertGuest();
    }

    protected function fakeSocialite(string $provider, SocialiteUser $user): void
    {
        $this->app->instance(SocialiteFactory::class, new FakeSocialiteFactory([
            $provider => new FakeOAuthProvider($user),
        ]));
    }

    protected function socialiteUser(array $attributes): SocialiteUser
    {
        $raw = $attributes['raw'] ?? [];

        return (new SocialiteUser)->setRaw($raw)->map([
            'id' => $attributes['id'] ?? null,
            'nickname' => $attributes['nickname'] ?? null,
            'name' => $attributes['name'] ?? null,
            'email' => $attributes['email'] ?? null,
            'avatar' => $attributes['avatar'] ?? null,
        ]);
    }

    protected function pendingProfile(array $overrides = []): array
    {
        return array_merge([
            'provider' => 'google',
            'provider_label' => 'Google',
            'provider_user_id' => 'provider-123',
            'email' => 'oauth@example.test',
            'email_verified' => true,
            'name' => 'Demo Customer',
            'first_name' => '',
            'last_name' => '',
            'nickname' => '',
            'suggested_nickname' => '',
            'avatar_url' => null,
            'raw_name' => 'Demo Customer',
        ], $overrides);
    }
}

class FakeSocialiteFactory implements SocialiteFactory
{
    public function __construct(protected array $providers) {}

    public function driver($driver = null): AbstractProvider
    {
        return $this->providers[$driver];
    }
}

class FakeOAuthProvider extends AbstractProvider
{
    public function __construct(protected SocialiteUser $fakeUser, protected ?Throwable $exception = null) {}

    public function redirect(): RedirectResponse
    {
        return redirect('https://oauth.example.test/authorize');
    }

    public function user(): SocialiteUser
    {
        if ($this->exception instanceof Throwable) {
            throw $this->exception;
        }

        return $this->fakeUser;
    }

    protected function getAuthUrl($state): string
    {
        return 'https://oauth.example.test/authorize';
    }

    protected function getTokenUrl(): string
    {
        return 'https://oauth.example.test/token';
    }

    protected function getUserByToken($token): array
    {
        return [];
    }

    protected function mapUserToObject(array $user): SocialiteUser
    {
        return $this->fakeUser;
    }
}
