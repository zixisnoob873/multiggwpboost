<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Support\OrderStatus;
use App\Support\Privacy\CookieConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TawkWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_support_widget_waits_for_support_cookie_consent_on_standard_pages(): void
    {
        $response = $this->get(route('login'));
        $html = $response->getContent();
        $bodyClosePosition = strrpos($html, '</body>');

        $response->assertOk();
        $response->assertDontSee('embed.tawk.to/69f292b86de35f1c378f957f/1jndoq8fv', false);
        $response->assertDontSee('Tawk_API', false);
        $response->assertSee('<title>Login | GGWP-Boost</title>', false);
        $this->assertNotFalse($bodyClosePosition);
        $response->assertSee('data-cookie-consent', false);

        $consentedResponse = $this->withSupportConsentCookie()->get(route('login'));
        $consentedHtml = $consentedResponse->getContent();
        $widgetPosition = strpos($consentedHtml, "https://embed.tawk.to/69f292b86de35f1c378f957f/1jndoq8fv");
        $bodyClosePosition = strrpos($consentedHtml, '</body>');

        $consentedResponse->assertOk();
        $consentedResponse->assertSee('embed.tawk.to/69f292b86de35f1c378f957f/1jndoq8fv', false);
        $consentedResponse->assertSee('Tawk_API', false);
        $this->assertNotFalse($widgetPosition);
        $this->assertNotFalse($bodyClosePosition);
        $this->assertLessThan($bodyClosePosition, $widgetPosition);

        $csp = (string) $consentedResponse->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('https://*.tawk.to', $csp);
        $this->assertStringContainsString('wss://*.tawk.to', $csp);
        $this->assertStringContainsString('frame-src', $csp);

        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $this->withSupportConsentCookie()
            ->actingAs($customer)
            ->get(route('customer-dashboard'))
            ->assertOk()
            ->assertSee('embed.tawk.to/69f292b86de35f1c378f957f/1jndoq8fv', false)
            ->assertSee('Tawk_API', false);
    }

    public function test_live_support_widget_is_absent_from_customer_chat_booster_and_admin_pages(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);
        $booster = User::factory()->create([
            'role' => 'booster',
            'account_status' => 'active',
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);

        $order = Order::query()->create([
            'user_id' => $customer->id,
            'booster_id' => $booster->id,
            'order_number' => (string) Str::orderedUuid(),
            'product' => 'Rank Boosting',
            'status' => OrderStatus::IN_PROGRESS,
            'payment_status' => 'paid',
            'price_cents' => 15000,
            'original_price_cents' => 15000,
            'booster_payout_rate' => Order::configuredBoosterPayoutPercentage(),
            'booster_payout_basis_cents' => 15000,
            'booster_payout_cents' => (int) round(15000 * Order::configuredBoosterPayoutRate()),
            'currency' => 'USD',
            'details' => [
                'service' => 'Rank Boosting',
                'from' => 'Silver I',
                'to' => 'Gold I',
                'order' => [
                    'orderType' => 'Rank Boosting',
                    'currentDivision' => 'Silver I',
                    'desiredDivision' => 'Gold I',
                    'region' => 'EU',
                    'platform' => 'PC',
                ],
            ],
            'metadata' => [
                'customer' => [
                    'email' => $customer->email,
                ],
                'contactMethod' => 'email',
            ],
            'paid_at' => now(),
            'assigned_at' => now(),
        ]);

        $this->withSupportConsentCookie()
            ->actingAs(User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]))
            ->get(route('user-chats'))
            ->assertOk()
            ->assertDontSee('data-live-chat-widget=', false)
            ->assertDontSee('embed.tawk.to', false)
            ->assertDontSee('Tawk_API', false);

        $customerChatResponse = $this->withSupportConsentCookie()
            ->actingAs($customer)
            ->get(route('user-chats.show', ['order' => $order]));

        $customerChatResponse
            ->assertOk()
            ->assertDontSee('data-live-chat-widget=', false)
            ->assertDontSee('embed.tawk.to', false)
            ->assertDontSee('Tawk_API', false);
        $this->assertStringNotContainsString('https://*.tawk.to', (string) $customerChatResponse->headers->get('Content-Security-Policy'));

        $boosterResponse = $this->withSupportConsentCookie()
            ->actingAs($booster)
            ->get(route('booster-dashboard'))
            ->assertOk();

        $boosterResponse
            ->assertDontSee('data-live-chat-widget=', false)
            ->assertDontSee('embed.tawk.to', false)
            ->assertDontSee('Tawk_API', false);
        $this->assertStringNotContainsString('https://*.tawk.to', (string) $boosterResponse->headers->get('Content-Security-Policy'));

        $adminResponse = $this->withSupportConsentCookie()
            ->actingAs($admin)
            ->get(route('admin-dashboard'))
            ->assertOk();

        $adminResponse
            ->assertDontSee('data-live-chat-widget=', false)
            ->assertDontSee('embed.tawk.to', false)
            ->assertDontSee('Tawk_API', false);
        $this->assertStringNotContainsString('https://*.tawk.to', (string) $adminResponse->headers->get('Content-Security-Policy'));
    }

    protected function withSupportConsentCookie(): self
    {
        return $this->withUnencryptedCookie(CookieConsent::COOKIE_NAME, rawurlencode(json_encode([
            'version' => CookieConsent::VERSION,
            'timestamp' => '2026-05-07T00:00:00+00:00',
            'categories' => [
                'necessary' => true,
                'analytics' => false,
                'support' => true,
            ],
        ], JSON_THROW_ON_ERROR)));
    }
}
