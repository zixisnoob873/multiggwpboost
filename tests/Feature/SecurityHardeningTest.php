<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Support\Privacy\CookieConsent;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_locks_out_after_too_many_failed_attempts(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
            'password' => 'ValidPass123!',
        ]);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $response = $this->from(route('login'))->post(route('login.submit'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);

            $response->assertRedirect(route('login'));
            $response->assertSessionHasErrors('email');
        }

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $response = $this->from(route('login'))->post(route('login.submit'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);

            $response->assertRedirect(route('login'));
            $response->assertSessionHasErrors('captcha');
        }

        $lockout = $this->from(route('login'))->post(route('login.submit'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $lockout->assertRedirect(route('login'));
        $lockout->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
    }

    public function test_login_errors_do_not_enumerate_suspended_accounts(): void
    {
        $active = User::factory()->create([
            'email' => 'active@example.test',
            'role' => 'customer',
            'account_status' => 'active',
            'password' => 'ValidPass123!',
        ]);
        $suspended = User::factory()->create([
            'email' => 'suspended@example.test',
            'role' => 'customer',
            'account_status' => 'suspended',
            'password' => 'ValidPass123!',
        ]);

        foreach ([
            ['email' => 'missing@example.test', 'password' => 'wrong-password'],
            ['email' => $active->email, 'password' => 'wrong-password'],
            ['email' => $suspended->email, 'password' => 'wrong-password'],
            ['email' => $suspended->email, 'password' => 'ValidPass123!'],
        ] as $payload) {
            $response = $this->from(route('login'))->post(route('login.submit'), $payload);

            $response->assertRedirect(route('login'));
            $response->assertSessionHasErrors(['email' => 'Invalid credentials.']);
            $this->assertGuest();
        }
    }

    public function test_contact_form_is_throttled(): void
    {
        $payload = [
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'order_reference' => 'ORD-123',
            'message' => str_repeat('Help me please. ', 3),
        ];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $response = $this->post(route('contact.submit'), $payload);
            $this->assertContains($response->getStatusCode(), [302, 429]);
        }

        $throttled = $this->post(route('contact.submit'), $payload);

        $throttled->assertStatus(429);
    }

    public function test_contact_form_rejects_messages_longer_than_six_hundred_characters(): void
    {
        $response = $this->from(route('contact'))->post(route('contact.submit'), [
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'order_reference' => 'ORD-600',
            'message' => str_repeat('A', 601),
            'website' => '',
        ]);

        $response->assertRedirect(route('contact'));
        $response->assertSessionHasErrors('message');
    }

    public function test_contact_page_exposes_the_six_hundred_character_limit_and_counter_feedback(): void
    {
        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('maxlength="600"', false)
            ->assertSee('data-character-count-output', false)
            ->assertSee('Use 20 to 600 characters.');
    }

    public function test_signup_requires_a_strong_twelve_character_password(): void
    {
        $response = $this->from(route('signup'))->post(route('signup.submit'), [
            'first_name' => 'Demo',
            'last_name' => 'Customer',
            'nickname' => 'DemoCustomer',
            'email' => 'demo@example.com',
            'password' => 'weakpass',
            'password_confirmation' => 'weakpass',
            'accepted_terms' => '1',
        ]);

        $response->assertRedirect(route('signup'));
        $response->assertSessionHasErrors('password');
    }

    public function test_signup_requires_accepting_the_terms_checkbox(): void
    {
        $response = $this->from(route('signup'))->post(route('signup.submit'), [
            'first_name' => 'Demo',
            'last_name' => 'Customer',
            'nickname' => 'DemoCustomer',
            'email' => 'demo@example.com',
            'password' => 'ValidPass123!',
            'password_confirmation' => 'ValidPass123!',
        ]);

        $response->assertRedirect(route('signup'));
        $response->assertSessionHasErrors('accepted_terms');
    }

    public function test_signup_accepts_a_strong_password(): void
    {
        $response = $this->post(route('signup.submit'), [
            'first_name' => 'Demo',
            'last_name' => 'Customer',
            'nickname' => 'DemoPlayer',
            'email' => 'simplepass@example.com',
            'password' => 'MuchBetter123!',
            'password_confirmation' => 'MuchBetter123!',
            'accepted_terms' => '1',
        ]);

        $response->assertRedirect(route('customer-dashboard'));
        $this->assertDatabaseHas('users', [
            'email' => 'simplepass@example.com',
            'nickname' => 'DemoPlayer',
        ]);
    }

    public function test_user_mass_assignment_does_not_accept_privileged_fields(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
            'password' => 'ValidPass123!',
            'profile_photo_path' => null,
        ]);

        $user->fill([
            'role' => User::ROLE_SUPER_ADMIN,
            'account_status' => 'suspended',
            'password' => 'ChangedPass123!',
            'profile_photo_path' => 'uploads/profile-photos/1/avatar.png',
        ])->save();

        $user->refresh();

        $this->assertSame('customer', $user->role);
        $this->assertSame('active', $user->account_status);
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('ChangedPass123!', $user->password));
        $this->assertNull($user->profile_photo_path);
    }

    public function test_password_update_requires_a_strong_twelve_character_password(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
            'password' => 'ValidPass123!',
        ]);

        $response = $this->actingAs($user)
            ->from(route('customer-dashboard'))
            ->post(route('user.password.update'), [
                'current_password' => 'ValidPass123!',
                'password' => 'weakpass',
                'password_confirmation' => 'weakpass',
            ]);

        $response->assertRedirect(route('customer-dashboard'));
        $response->assertSessionHasErrors('password');
    }

    public function test_production_user_seeder_refuses_fixed_demo_credentials(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('refuses to create fixed demo credentials');

        app(UserSeeder::class)->run();
    }

    public function test_pending_checkout_payloads_are_encrypted_at_rest(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $store = app(\App\Services\Payments\PendingCheckoutStore::class);
        $pendingCheckout = $store->create($customer->id, new \App\Data\Payments\PaymentCheckoutData(
            requestData: [
                'firstName' => 'Private',
                'lastName' => 'Customer',
                'email' => 'private.customer@example.test',
                'contactMethod' => 'discord',
                'whatsapp' => null,
                'discord' => 'private#1234',
            ],
            orderPayload: [
                'orderType' => 'Rank Boosting',
                'currentDivision' => 'Gold I',
                'desiredDivision' => 'Platinum I',
            ],
            paymentMethod: 'stripe',
            priceCents: 1999,
            total: 19.99,
            subtotal: 19.99,
        ));

        $raw = DB::table('pending_checkouts')
            ->where('token', $pendingCheckout->token)
            ->first();

        $this->assertIsString($raw->request_data);
        $this->assertStringNotContainsString('private.customer@example.test', $raw->request_data);
        $this->assertStringNotContainsString('private#1234', $raw->request_data);

        $reloaded = $store->find($pendingCheckout->token);

        $this->assertSame('private.customer@example.test', $reloaded?->requestData['email']);
        $this->assertSame('private#1234', $reloaded?->requestData['discord']);
    }

    public function test_security_headers_are_sent_on_public_pages(): void
    {
        $response = $this->get(route('home'));
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $response->assertOk();
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("connect-src 'self'", $csp);
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline'", $csp);
    }

    public function test_security_headers_are_single_valued_in_app_responses(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $this->assertSame(['nosniff'], $response->headers->all('X-Content-Type-Options'));
        $this->assertSame(['strict-origin-when-cross-origin'], $response->headers->all('Referrer-Policy'));
        $this->assertSame(['DENY'], $response->headers->all('X-Frame-Options'));
    }

    public function test_csp_form_action_allows_hosted_payment_redirects(): void
    {
        config()->set('services.stripe.enabled', true);
        config()->set('services.cryptomus.enabled', true);

        $response = $this->get(route('checkout'));
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $response->assertOk();
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString('https://checkout.stripe.com', $csp);
        $this->assertStringContainsString('https://pay.cryptomus.com', $csp);
    }

    public function test_production_https_sends_required_security_headers(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $response = $this->get('https://localhost/');

        $response->assertOk();
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertStringContainsString("frame-ancestors 'none'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertSame('camera=(), geolocation=(), microphone=(), payment=(), usb=()', $response->headers->get('Permissions-Policy'));
        $this->assertSame('max-age=31536000; includeSubDomains; preload', $response->headers->get('Strict-Transport-Security'));
    }

    public function test_production_https_overwrites_app_level_x_frame_options_values(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        Route::get('/_test/app-x-frame-options', fn () => response('ok')->header('X-Frame-Options', 'SAMEORIGIN'));

        $response = $this->get('https://localhost/_test/app-x-frame-options');

        $response->assertOk();
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertStringContainsString("frame-ancestors 'none'", (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_apache_public_entrypoint_clears_inherited_duplicate_security_headers(): void
    {
        $htaccess = (string) file_get_contents(public_path('.htaccess'));

        $this->assertStringContainsString('Header onsuccess unset X-Content-Type-Options', $htaccess);
        $this->assertStringContainsString('Header always unset X-Content-Type-Options', $htaccess);
        $this->assertStringContainsString('Header always set X-Content-Type-Options "nosniff"', $htaccess);
        $this->assertStringContainsString('Header onsuccess unset Referrer-Policy', $htaccess);
        $this->assertStringContainsString('Header always unset Referrer-Policy', $htaccess);
        $this->assertStringContainsString('Header always set Referrer-Policy "strict-origin-when-cross-origin"', $htaccess);
        $this->assertStringContainsString('Header onsuccess unset X-Frame-Options', $htaccess);
        $this->assertStringContainsString('Header always unset X-Frame-Options', $htaccess);
        $this->assertStringContainsString('Header always set X-Frame-Options "DENY"', $htaccess);
        $this->assertStringNotContainsString('SAMEORIGIN', $htaccess);
    }

    public function test_production_csp_excludes_local_development_connect_sources(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        config()->set('broadcasting.default', 'pusher');
        config()->set('broadcasting.connections.pusher.key', 'prod-chat-key');
        config()->set('broadcasting.connections.pusher.options.host', '127.0.0.1');
        config()->set('broadcasting.connections.pusher.options.port', 6001);
        config()->set('broadcasting.connections.pusher.options.scheme', 'http');
        config()->set('analytics.google.measurement_id', 'G-TESTANALYTICS');

        $response = $this->get('https://localhost/');
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $response->assertOk();
        $this->assertStringContainsString("connect-src 'self'", $csp);
        $this->assertStringContainsString('https://www.googletagmanager.com', $csp);
        $this->assertStringContainsString('https://*.googletagmanager.com', $csp);
        $this->assertStringContainsString('https://www.google-analytics.com', $csp);
        $this->assertStringContainsString('https://*.google-analytics.com', $csp);
        $this->assertStringContainsString('https://analytics.google.com', $csp);
        $this->assertStringContainsString('https://*.tawk.to', $csp);
        $this->assertStringContainsString('wss://*.tawk.to', $csp);
        $this->assertStringNotContainsString('http://127.0.0.1:6001', $csp);
        $this->assertStringNotContainsString('ws://127.0.0.1:6001', $csp);
    }

    public function test_production_csp_allows_configured_https_websocket_host(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        config()->set('broadcasting.default', 'pusher');
        config()->set('broadcasting.connections.pusher.key', 'prod-chat-key');
        config()->set('broadcasting.connections.pusher.options.host', 'ws.ggwp.example');
        config()->set('broadcasting.connections.pusher.options.port', 443);
        config()->set('broadcasting.connections.pusher.options.scheme', 'https');

        $response = $this->get('https://localhost/');
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $response->assertOk();
        $this->assertStringContainsString('https://ws.ggwp.example:443', $csp);
        $this->assertStringContainsString('wss://ws.ggwp.example:443', $csp);
    }

    public function test_production_session_and_xsrf_cookie_flags_are_preserved(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        config()->set('session.secure', true);
        config()->set('session.http_only', true);
        config()->set('session.same_site', 'lax');

        $response = $this->get('https://localhost/login');
        $cookies = collect($response->headers->getCookies())->keyBy(fn ($cookie) => $cookie->getName());
        $sessionCookie = $cookies->get(config('session.cookie'));
        $xsrfCookie = $cookies->get('XSRF-TOKEN');

        $response->assertOk();
        $this->assertNotNull($sessionCookie);
        $this->assertTrue($sessionCookie->isSecure());
        $this->assertTrue($sessionCookie->isHttpOnly());
        $this->assertSame('lax', strtolower((string) $sessionCookie->getSameSite()));
        $this->assertNotNull($xsrfCookie);
        $this->assertTrue($xsrfCookie->isSecure());
        $this->assertFalse($xsrfCookie->isHttpOnly());
        $this->assertSame('lax', strtolower((string) $xsrfCookie->getSameSite()));
    }

    public function test_login_form_posts_to_relative_action_to_preserve_current_scheme(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('action="/login"', false)
            ->assertSee("form[action=\"/login\"]", false);
    }

    public function test_google_tag_is_rendered_on_public_pages_and_allowed_by_csp(): void
    {
        $response = $this->withAnalyticsConsentCookie()->get(route('home'));
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $response->assertOk()
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TESTANALYTICS', false)
            ->assertSee('window.gtag(\'config\', "G-TESTANALYTICS");', false)
            ->assertDontSee('G-9J3GNV5WSX', false);

        $this->assertSame(1, substr_count($response->getContent(), 'gtag/js?id=G-TESTANALYTICS'));
        $this->assertSame(1, substr_count($response->getContent(), 'window.gtag(\'config\', "G-TESTANALYTICS");'));
        $this->assertStringContainsString('https://www.googletagmanager.com', $csp);
        $this->assertStringContainsString('https://*.googletagmanager.com', $csp);
        $this->assertStringContainsString('https://www.google-analytics.com', $csp);
        $this->assertStringContainsString('https://*.google-analytics.com', $csp);
    }

    public function test_posthog_analytics_config_is_allowed_by_csp_when_enabled(): void
    {
        config()->set('analytics.posthog.key', 'phc_test');
        config()->set('analytics.posthog.host', 'https://us.i.posthog.com');

        $response = $this->withAnalyticsConsentCookie()->get(route('home'));
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $response->assertOk()
            ->assertSee('phc_test', false)
            ->assertSee('"hasPostHog":true', false);

        $this->assertStringContainsString('https://us.i.posthog.com', $csp);
    }

    public function test_google_tag_is_rendered_across_relevant_shared_layout_pages(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);
        $booster = User::factory()->create([
            'role' => 'booster',
            'account_status' => 'active',
        ]);

        foreach ([
            route('home'),
            route('checkout'),
            route('contact'),
            route('faq'),
            route('blog.index'),
            route('terms-and-conditions'),
            route('login'),
            route('signup'),
            route('under-maintenance'),
        ] as $url) {
            $response = $this->withAnalyticsConsentCookie()->get($url);

            $response->assertOk()
                ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TESTANALYTICS', false);
            $this->assertSame(1, substr_count($response->getContent(), 'gtag/js?id=G-TESTANALYTICS'), $url);
        }

        $customerResponse = $this->withAnalyticsConsentCookie()
            ->actingAs($customer)
            ->get(route('customer-dashboard'));
        $customerResponse->assertOk()
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TESTANALYTICS', false);
        $this->assertSame(1, substr_count($customerResponse->getContent(), 'gtag/js?id=G-TESTANALYTICS'));

        $boosterResponse = $this->withAnalyticsConsentCookie()
            ->actingAs($booster)
            ->get(route('booster-dashboard'));
        $boosterResponse->assertOk()
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TESTANALYTICS', false);
        $this->assertSame(1, substr_count($boosterResponse->getContent(), 'gtag/js?id=G-TESTANALYTICS'));
    }

    public function test_google_tag_is_rendered_on_admin_pages_too(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'account_status' => 'active',
        ]);

        $response = $this->withAnalyticsConsentCookie()
            ->actingAs($admin)
            ->get(route('admin-dashboard'))
            ->assertOk()
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TESTANALYTICS', false)
            ->assertSee('window.gtag(\'config\', "G-TESTANALYTICS");', false);

        $this->assertSame(1, substr_count($response->getContent(), 'gtag/js?id=G-TESTANALYTICS'));
        $this->assertStringContainsString('googletagmanager.com', (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('google-analytics.com', (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_json_ld_schema_escapes_script_breakout_sequences(): void
    {
        $html = Blade::render('@include("partials.seo-meta", ["seo" => $seo])', [
            'seo' => [
                'schema' => [
                    'name' => '</script><script>alert(1)</script>',
                ],
            ],
            'cspNonce' => 'test-nonce',
        ]);

        $this->assertStringNotContainsString('</script><script>alert(1)</script>', $html);
        $this->assertStringContainsString('\u003C/script\u003E', $html);
        $this->assertSame(1, substr_count($html, '<script'));
    }

    public function test_authenticated_pages_are_not_browser_cacheable(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $response = $this->actingAs($user)->get(route('customer-dashboard'));

        $response->assertOk();
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
        $this->assertSame('0', $response->headers->get('Expires'));
    }

    public function test_profile_photo_upload_is_sanitized_and_stored_on_the_private_disk(): void
    {
        Storage::fake('private');

        $user = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->post(route('user.profile-photo.update'), [
                'profile_photo' => UploadedFile::fake()->image('avatar.evil.png', 300, 300),
            ]);

        $response->assertRedirect(route('customer-dashboard'));

        $user->refresh();

        $this->assertNotNull($user->profile_photo_path);
        $this->assertStringStartsWith('uploads/profile-photos/'.$user->id.'/', $user->profile_photo_path);
        $this->assertStringNotContainsString('avatar.evil', $user->profile_photo_path);
        Storage::disk('private')->assertExists($user->profile_photo_path);
    }

    #[DataProvider('validProfilePhotoFormatProvider')]
    public function test_valid_profile_photo_uploads_succeed_for_supported_image_formats(string $filename, string $encoder): void
    {
        if (! function_exists($encoder)) {
            $this->markTestSkipped($encoder.' is not available in this PHP build.');
        }

        Storage::fake('private');

        $user = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->post(route('user.profile-photo.update'), [
                'profile_photo' => UploadedFile::fake()->image($filename, 300, 300),
            ]);

        $response->assertRedirect(route('customer-dashboard'));
        $response->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertNotNull($user->profile_photo_path);
        $this->assertStringStartsWith('uploads/profile-photos/'.$user->id.'/', $user->profile_photo_path);
        Storage::disk('private')->assertExists($user->profile_photo_path);
    }

    public function test_php_payload_masquerading_as_image_upload_fails(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->from(route('customer-dashboard'))
            ->post(route('user.profile-photo.update'), [
                'profile_photo' => UploadedFile::fake()->createWithContent('avatar.png', '<?php echo "owned";'),
            ]);

        $response->assertRedirect(route('customer-dashboard'));
        $response->assertSessionHasErrors('profile_photo');
    }

    public function test_svg_profile_photo_upload_fails(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->from(route('customer-dashboard'))
            ->post(route('user.profile-photo.update'), [
                'profile_photo' => UploadedFile::fake()->createWithContent('avatar.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
            ]);

        $response->assertRedirect(route('customer-dashboard'));
        $response->assertSessionHasErrors('profile_photo');
    }

    public function test_extreme_dimension_profile_photo_upload_fails_before_decode(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->from(route('customer-dashboard'))
            ->post(route('user.profile-photo.update'), [
                'profile_photo' => $this->fakePngUploadWithDimensions('huge.png', 10000, 3000),
            ]);

        $response->assertRedirect(route('customer-dashboard'));
        $response->assertSessionHasErrors('profile_photo');
        $this->assertSame('Uploaded image dimensions are not permitted.', session('errors')->first('profile_photo'));
    }

    public function test_boosters_cannot_access_customer_checkout_or_dashboard_routes(): void
    {
        $booster = User::factory()->create([
            'role' => 'booster',
            'account_status' => 'active',
        ]);

        $this->actingAs($booster)
            ->get(route('customer-dashboard'))
            ->assertForbidden();

        $this->actingAs($booster)
            ->post(route('checkout.submit'), [
                'firstName' => 'Demo',
                'lastName' => 'Booster',
                'email' => 'booster@example.com',
                'contactMethod' => 'discord',
                'discord' => 'booster#1234',
                'orderPayload' => json_encode([
                    'serviceType' => 'Rank Boosting',
                    'currentDivision' => 'Gold I',
                    'targetDivision' => 'Platinum I',
                    'currentRR' => 0,
                    'avgRRPerWin' => '20',
                    'region' => 'NA',
                    'platform' => 'PC',
                    'boostMode' => 'normal',
                    'selectedAddons' => [],
                ], JSON_THROW_ON_ERROR),
                'paymentMethod' => 'stripe',
                'policy' => '1',
                'compliance' => '1',
            ])
            ->assertForbidden();
    }

    public function test_booster_accounts_see_purchase_flows_blocked_on_the_frontend(): void
    {
        $booster = User::factory()->create([
            'role' => 'booster',
            'account_status' => 'active',
        ]);

        $this->actingAs($booster)
            ->get(route('checkout'))
            ->assertOk()
            ->assertSee('Booster accounts cannot buy services.')
            ->assertDontSee('id="checkoutForm"', false);

        $this->actingAs($booster)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Booster accounts cannot place customer orders.')
            ->assertSee('Open Booster Dashboard');
    }

    public function test_super_admins_cannot_access_booster_workspace_routes(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'account_status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('booster-dashboard'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('booster-wallet'))
            ->assertForbidden();
    }

    public function test_admin_order_csv_export_escapes_formula_cells(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'account_status' => 'active',
        ]);
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
            'first_name' => '=SUM(1',
            'last_name' => '2)',
            'name' => '=SUM(1 2)',
            'email' => 'customer@example.test',
        ]);

        Order::create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-SECURITY-CSV',
            'product' => 'Rank Boosting',
            'status' => 'Pending',
            'payment_status' => 'paid',
            'price_cents' => 1999,
            'currency' => 'USD',
            'details' => ['order' => ['orderType' => 'Rank Boosting']],
            'metadata' => [],
            'contact_method' => 'email',
            'is_custom' => false,
        ]);

        $content = $this->actingAs($admin)
            ->get(route('admin-total-order.export'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString("'=SUM(1 2)", $content);
    }

    protected function fakePngUploadWithDimensions(string $name, int $width, int $height): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'huge-png-');
        $ihdrData = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        $ihdrChunk = pack('N', strlen($ihdrData)).'IHDR'.$ihdrData.pack('N', crc32('IHDR'.$ihdrData));
        $iendChunk = pack('N', 0).'IEND'.pack('N', crc32('IEND'));

        file_put_contents($path, "\x89PNG\x0D\x0A\x1A\x0A".$ihdrChunk.$iendChunk);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    protected function withAnalyticsConsentCookie(): self
    {
        config()->set('analytics.google.measurement_id', 'G-TESTANALYTICS');

        return $this->withUnencryptedCookie(CookieConsent::COOKIE_NAME, rawurlencode(json_encode([
            'version' => CookieConsent::VERSION,
            'timestamp' => '2026-05-07T00:00:00+00:00',
            'categories' => [
                'necessary' => true,
                'analytics' => true,
                'support' => false,
            ],
        ], JSON_THROW_ON_ERROR)));
    }

    public static function validProfilePhotoFormatProvider(): array
    {
        return [
            'jpg' => ['avatar.jpg', 'imagejpeg'],
            'png' => ['avatar.png', 'imagepng'],
            'webp' => ['avatar.webp', 'imagewebp'],
        ];
    }
}
