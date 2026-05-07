<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SystemSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MaintenanceModeFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    public function test_missing_setting_defaults_to_off(): void
    {
        $this->assertFalse(app(SystemSettingService::class)->isMaintenanceModeEnabled());
        $this->assertDatabaseCount('system_settings', 0);

        $this->get(route('home'))
            ->assertOk();
    }

    public function test_admin_can_toggle_maintenance_mode_on_and_off_via_ajax(): void
    {
        $admin = $this->makeAdmin();

        $enableResponse = $this->completeMaintenanceToggle($admin, true);

        $enableResponse
            ->assertOk()
            ->assertJson([
                'enabled' => true,
                'label' => 'ON',
                'message' => 'Maintenance mode is now ON.',
            ]);

        $this->assertTrue(app(SystemSettingService::class)->isMaintenanceModeEnabled());
        $this->assertDatabaseHas('system_settings', [
            'key' => SystemSettingService::MAINTENANCE_MODE_KEY,
            'value' => '1',
        ]);

        Cache::flush();

        $disableResponse = $this->completeMaintenanceToggle($admin, false);

        $disableResponse
            ->assertOk()
            ->assertJson([
                'enabled' => false,
                'label' => 'OFF',
                'message' => 'Maintenance mode is now OFF.',
            ]);

        $this->assertFalse(app(SystemSettingService::class)->isMaintenanceModeEnabled());
        $this->assertDatabaseHas('system_settings', [
            'key' => SystemSettingService::MAINTENANCE_MODE_KEY,
            'value' => '0',
        ]);
    }

    public function test_non_admin_users_cannot_toggle_maintenance_mode(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $this->actingAs($customer)
            ->postJson(route('admin-maintenance-mode.challenge'), [
                'enabled' => true,
            ])
            ->assertForbidden();

        $this->actingAs($customer)
            ->patchJson(route('admin-maintenance-mode.update'), [
                'enabled' => true,
                'flow_token' => (string) \Illuminate\Support\Str::uuid(),
                'final_confirmation' => true,
            ])
            ->assertForbidden();

        $this->assertFalse(app(SystemSettingService::class)->isMaintenanceModeEnabled());
    }

    public function test_direct_update_without_completed_flow_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $flowToken = $this->startMaintenanceFlow($admin, true);

        $this->actingAs($admin)
            ->patchJson(route('admin-maintenance-mode.update'), [
                'enabled' => true,
                'flow_token' => $flowToken,
                'final_confirmation' => true,
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'restart_required' => true,
            ]);

        $this->assertFalse(app(SystemSettingService::class)->isMaintenanceModeEnabled());
    }

    public function test_incorrect_captcha_regenerates_challenge_and_does_not_toggle(): void
    {
        $admin = $this->makeAdmin();
        $flowToken = $this->startMaintenanceFlow($admin, true);

        $confirmResponse = $this->actingAs($admin)
            ->postJson(route('admin-maintenance-mode.confirm'), [
                'enabled' => true,
                'flow_token' => $flowToken,
                'confirmation_text' => 'CONFIRM',
            ]);

        $confirmResponse->assertOk();
        $originalCaptcha = (string) $confirmResponse->json('challenge.captcha');
        $wrongCaptcha = $originalCaptcha === '000000' ? '111111' : '000000';

        $captchaResponse = $this->actingAs($admin)
            ->postJson(route('admin-maintenance-mode.captcha'), [
                'enabled' => true,
                'flow_token' => $flowToken,
                'captcha' => $wrongCaptcha,
            ]);

        $captchaResponse
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'step' => 'captcha',
            ]);

        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $captchaResponse->json('challenge.captcha'));
        $this->assertFalse(app(SystemSettingService::class)->isMaintenanceModeEnabled());
    }

    public function test_incorrect_password_does_not_toggle_maintenance_mode(): void
    {
        $admin = $this->makeAdmin();
        $flowToken = $this->startMaintenanceFlow($admin, true);
        $captcha = $this->advanceThroughCaptcha($admin, $flowToken, true);

        $this->actingAs($admin)
            ->postJson(route('admin-maintenance-mode.password'), [
                'enabled' => true,
                'flow_token' => $flowToken,
                'current_password' => 'wrong-password',
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'step' => 'password',
            ]);

        $this->assertSame(6, strlen($captcha));
        $this->assertFalse(app(SystemSettingService::class)->isMaintenanceModeEnabled());
    }

    public function test_public_pages_redirect_to_under_maintenance_when_enabled(): void
    {
        app(SystemSettingService::class)->setMaintenanceMode(true);

        $this->get(route('home'))
            ->assertRedirect(route('under-maintenance'));
    }

    public function test_blog_auth_and_admin_routes_remain_accessible_during_maintenance_mode(): void
    {
        app(SystemSettingService::class)->setMaintenanceMode(true);
        $admin = $this->makeAdmin();

        $this->get(route('blog.index'))
            ->assertOk();

        $this->get(route('login'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin-dashboard'))
            ->assertOk()
            ->assertSee('Maintenance Mode');
    }

    public function test_under_maintenance_page_does_not_redirect_loop_and_uses_discord_link(): void
    {
        app(SystemSettingService::class)->setMaintenanceMode(true);

        $response = $this->get(route('under-maintenance'));

        $response
            ->assertOk()
            ->assertSeeText('Website is under maintenance right now, please visit back in 5-10 minutes. If you want to place your order urgent, join our')
            ->assertSeeText('Discord')
            ->assertSeeText('and open a ticket, our support will be in touch with you within 5 minutes.')
            ->assertSee(config('footer.support.community_url'), false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false);
    }

    public function test_ajax_requests_return_json_503_instead_of_redirecting(): void
    {
        app(SystemSettingService::class)->setMaintenanceMode(true);

        $this->getJson(route('home'))
            ->assertStatus(503)
            ->assertJson([
                'redirect_to' => route('under-maintenance'),
            ]);
    }

    public function test_payment_and_operational_routes_are_not_redirected_during_maintenance_mode(): void
    {
        app(SystemSettingService::class)->setMaintenanceMode(true);

        $ordersSuccess = $this->get(route('orders.success'));
        $faqApi = $this->get(route('api.faqs'));

        $this->assertContains($ordersSuccess->getStatusCode(), [400, 302]);
        $this->assertNotSame(route('under-maintenance'), $ordersSuccess->headers->get('Location'));
        $faqApi->assertOk();
    }

    protected function makeAdmin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'account_status' => 'active',
            'password' => Hash::make('password'),
        ]);
    }

    protected function completeMaintenanceToggle(User $admin, bool $enabled): TestResponse
    {
        $flowToken = $this->startMaintenanceFlow($admin, $enabled);
        $this->advanceThroughCaptcha($admin, $flowToken, $enabled);

        $this->actingAs($admin)
            ->postJson(route('admin-maintenance-mode.password'), [
                'enabled' => $enabled,
                'flow_token' => $flowToken,
                'current_password' => 'password',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'step' => 4,
            ]);

        return $this->actingAs($admin)
            ->patchJson(route('admin-maintenance-mode.update'), [
                'enabled' => $enabled,
                'flow_token' => $flowToken,
                'final_confirmation' => true,
            ]);
    }

    protected function startMaintenanceFlow(User $admin, bool $enabled): string
    {
        $response = $this->actingAs($admin)
            ->postJson(route('admin-maintenance-mode.challenge'), [
                'enabled' => $enabled,
            ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        return (string) $response->json('flow.token');
    }

    protected function advanceThroughCaptcha(User $admin, string $flowToken, bool $enabled): string
    {
        $confirmResponse = $this->actingAs($admin)
            ->postJson(route('admin-maintenance-mode.confirm'), [
                'enabled' => $enabled,
                'flow_token' => $flowToken,
                'confirmation_text' => 'CONFIRM',
            ]);

        $confirmResponse
            ->assertOk()
            ->assertJson([
                'success' => true,
                'step' => 2,
            ]);

        $captcha = (string) $confirmResponse->json('challenge.captcha');

        $this->actingAs($admin)
            ->postJson(route('admin-maintenance-mode.captcha'), [
                'enabled' => $enabled,
                'flow_token' => $flowToken,
                'captcha' => $captcha,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'step' => 3,
            ]);

        return $captcha;
    }
}
