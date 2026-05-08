<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FooterRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_render_the_minimal_footer_with_updated_socials(): void
    {
        config([
            'footer.socials.0.url' => 'https://discord.gg/2FD3qq9U',
            'footer.socials.1.url' => 'https://instagram.com/ggwpboost',
            'footer.socials.2.url' => 'https://facebook.com/ggwpboost',
            'footer.socials.3.url' => 'https://twitter.com/ggwpboost',
            'footer.socials.4.url' => 'https://youtube.com/@ggwpboost',
            'footer.socials.5.url' => 'https://twitch.tv/ggwpboost',
        ]);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('ggwp-footer', false)
            ->assertSee('Premium Game Boosting Services for Every Competitive Title')
            ->assertSee(route('become-booster'), false)
            ->assertSee(route('terms-and-conditions'), false)
            ->assertSee(route('privacy-policy'), false)
            ->assertSee(route('refund-policy'), false)
            ->assertSee(route('code-of-ethics'), false)
            ->assertSee(asset('assets/socials/discord.svg'), false)
            ->assertSee(asset('assets/socials/instagram.svg'), false)
            ->assertSee(asset('assets/socials/facebook.svg'), false)
            ->assertSee(asset('assets/socials/twitter.svg'), false)
            ->assertSee(asset('assets/socials/youtube.svg'), false)
            ->assertSee(asset('assets/socials/twitch.svg'), false)
            ->assertSee('href="/favicon.svg"', false);

        $response->assertDontSee('Premium support for every order')
            ->assertDontSee('Live chat ready')
            ->assertDontSee('Company')
            ->assertDontSee('Customer Support')
            ->assertDontSee('Explore')
            ->assertDontSee('ggwp-footer__support-card', false)
            ->assertDontSee('ggwp-footer__legal-links', false)
            ->assertDontSee('ggwp-footer__social-btn--disabled', false);
    }

    public function test_privacy_policy_page_is_publicly_available(): void
    {
        $this->get(route('privacy-policy'))
            ->assertOk()
            ->assertSee('Privacy Policy');
    }

    public function test_admin_pages_do_not_render_the_public_footer(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'account_status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('admin-dashboard'))
            ->assertOk()
            ->assertDontSee('ggwp-footer', false);
    }
}
