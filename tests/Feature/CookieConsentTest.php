<?php

namespace Tests\Feature;

use App\Support\Privacy\CookieConsent;
use Tests\TestCase;

class CookieConsentTest extends TestCase
{
    public function test_layout_does_not_render_third_party_script_urls_without_cookie_consent(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertDontSee('https://www.googletagmanager.com/gtag/js?id=G-9J3GNV5WSX', false)
            ->assertDontSee('https://embed.tawk.to/69f292b86de35f1c378f957f/1jndoq8fv', false)
            ->assertDontSee('Tawk_API', false)
            ->assertSee('data-cookie-consent', false)
            ->assertSee('Cookie preferences')
            ->assertSee('data-cookie-accept-all', false)
            ->assertSee('data-cookie-decline', false)
            ->assertSee('data-cookie-customize', false);
    }

    public function test_analytics_consent_renders_google_tag(): void
    {
        $response = $this->withConsentCookie(analytics: true, support: false)
            ->get(route('home'));

        $response->assertOk()
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-9J3GNV5WSX', false)
            ->assertSee("gtag('config', 'G-9J3GNV5WSX');", false)
            ->assertDontSee('https://embed.tawk.to/69f292b86de35f1c378f957f/1jndoq8fv', false);
    }

    public function test_support_consent_renders_tawk_widget(): void
    {
        $response = $this->withConsentCookie(analytics: false, support: true)
            ->get(route('login'));

        $response->assertOk()
            ->assertDontSee('https://www.googletagmanager.com/gtag/js?id=G-9J3GNV5WSX', false)
            ->assertSee('https://embed.tawk.to/69f292b86de35f1c378f957f/1jndoq8fv', false)
            ->assertSee('Tawk_API', false);
    }

    public function test_outdated_consent_version_shows_banner_and_blocks_optional_scripts(): void
    {
        $response = $this->withConsentCookie(analytics: true, support: true, version: CookieConsent::VERSION - 1)
            ->get(route('home'));

        $response->assertOk()
            ->assertDontSee('https://www.googletagmanager.com/gtag/js?id=G-9J3GNV5WSX', false)
            ->assertDontSee('https://embed.tawk.to/69f292b86de35f1c378f957f/1jndoq8fv', false)
            ->assertSee('data-cookie-consent', false)
            ->assertSee('Cookie preferences');
    }

    public function test_current_declined_consent_keeps_banner_hidden_and_blocks_optional_scripts(): void
    {
        $response = $this->withConsentCookie(analytics: false, support: false)
            ->get(route('home'));
        $html = $response->getContent();

        $response->assertOk()
            ->assertDontSee('https://www.googletagmanager.com/gtag/js?id=G-9J3GNV5WSX', false)
            ->assertDontSee('https://embed.tawk.to/69f292b86de35f1c378f957f/1jndoq8fv', false)
            ->assertSee('data-cookie-consent', false);

        $this->assertMatchesRegularExpression('/<section[^>]+data-cookie-consent[^>]+hidden/s', $html);
    }

    public function test_necessary_session_and_xsrf_cookies_are_still_sent_without_optional_consent(): void
    {
        $response = $this->get(route('login'));
        $cookies = collect($response->headers->getCookies())->keyBy(fn ($cookie) => $cookie->getName());

        $response->assertOk();
        $this->assertNotNull($cookies->get(config('session.cookie')));
        $this->assertNotNull($cookies->get('XSRF-TOKEN'));
    }

    protected function withConsentCookie(bool $analytics, bool $support, int $version = CookieConsent::VERSION): self
    {
        return $this->withUnencryptedCookie(CookieConsent::COOKIE_NAME, rawurlencode(json_encode([
            'version' => $version,
            'timestamp' => '2026-05-07T00:00:00+00:00',
            'categories' => [
                'necessary' => true,
                'analytics' => $analytics,
                'support' => $support,
            ],
        ], JSON_THROW_ON_ERROR)));
    }
}
