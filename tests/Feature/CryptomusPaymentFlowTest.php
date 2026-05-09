<?php

namespace Tests\Feature;

use App\Data\Payments\PaymentCheckoutData;
use App\Models\Order;
use App\Models\PaymentWebhookEvent;
use App\Models\PendingCheckoutRecord;
use App\Models\User;
use App\Services\Payments\CryptomusClient;
use App\Services\Payments\PendingCheckoutStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use LogicException;
use Tests\TestCase;

class CryptomusPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_page_lists_stripe_and_cryptomus_gateways_even_before_credentials_are_added(): void
    {
        config()->set('services.stripe.enabled', true);
        config()->set('services.stripe.secret', null);
        config()->set('services.cryptomus.enabled', true);
        config()->set('services.cryptomus.merchant_id', null);
        config()->set('services.cryptomus.api_key', null);

        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $response = $this->actingAs($customer)->get(route('checkout'));

        $response->assertOk()
            ->assertSee('Instant card payment (Stripe)')
            ->assertSee('Crypto payment (Cryptomus)')
            ->assertSee('Setup needed');
    }

    public function test_cryptomus_checkout_redirects_to_a_hosted_invoice_and_persists_gateway_metadata(): void
    {
        Http::fake([
            'https://api.cryptomus.com/v1/payment' => Http::response([
                'state' => 0,
                'result' => [
                    'uuid' => 'cm_invoice_123',
                    'order_id' => 'CHK-TESTCRYPT',
                    'amount' => '41.29',
                    'payment_status' => 'check',
                    'status' => 'check',
                    'url' => 'https://pay.cryptomus.com/pay/cm_invoice_123',
                ],
            ], 200),
        ]);

        config()->set('services.cryptomus.enabled', true);
        config()->set('services.cryptomus.merchant_id', 'merchant_123');
        config()->set('services.cryptomus.api_key', 'cryptomus_api_key');
        config()->set('services.cryptomus.base_url', 'https://api.cryptomus.com');

        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $response = $this->actingAs($customer)
            ->from(route('checkout'))
            ->post(route('checkout.submit'), [
                'firstName' => 'Demo',
                'lastName' => 'Customer',
                'email' => $customer->email,
                'contactMethod' => 'email',
                'whatsapp' => null,
                'discord' => null,
                'paymentMethod' => 'cryptomus',
                'policy' => '1',
                'compliance' => '1',
                'orderPayload' => json_encode([
                    'serviceType' => 'Rank Boosting',
                    'currentDivision' => 'Gold II',
                    'desiredDivision' => 'Platinum II',
                    'currentRR' => 55,
                    'avgRRPerWin' => '16 OR LOWER',
                    'region' => 'NA',
                    'platform' => 'PC',
                    'boostMode' => 'Account Shared',
                    'selectedAddons' => ['Solo-Queue Only', 'Offline Mode'],
                ], JSON_THROW_ON_ERROR),
            ]);

        $response->assertRedirect('https://pay.cryptomus.com/pay/cm_invoice_123');
        $this->assertSame(0, Order::count());

        $pendingRecord = PendingCheckoutRecord::query()->firstOrFail();

        $this->assertSame('cryptomus', $pendingRecord->payment_method);
        $this->assertSame('cryptomus', data_get($pendingRecord->metadata, 'paymentProvider'));
        $this->assertSame('cm_invoice_123', data_get($pendingRecord->metadata, 'cryptomusInvoiceUuid'));
        $this->assertSame($pendingRecord->reference, data_get($pendingRecord->metadata, 'cryptomusOrderId'));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.cryptomus.com/v1/payment'
                && $request->hasHeader('merchant', 'merchant_123')
                && $request->hasHeader('sign');
        });
    }

    public function test_cryptomus_success_page_verifies_the_invoice_and_creates_the_order(): void
    {
        Http::fake([
            'https://api.cryptomus.com/v1/payment/info' => Http::response([
                'state' => 0,
                'result' => [
                    'uuid' => 'cm_invoice_paid',
                    'order_id' => 'CHK-CRYPTOSUCCESS',
                    'amount' => '45.99',
                    'payment_status' => 'paid',
                    'status' => 'paid',
                    'txid' => 'txid_paid_success',
                    'network' => 'tron',
                    'payer_currency' => 'USDT',
                ],
            ], 200),
        ]);

        config()->set('services.cryptomus.enabled', true);
        config()->set('services.cryptomus.merchant_id', 'merchant_123');
        config()->set('services.cryptomus.api_key', 'cryptomus_api_key');
        config()->set('services.cryptomus.base_url', 'https://api.cryptomus.com');

        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $checkoutData = new PaymentCheckoutData(
            requestData: [
                'firstName' => 'Demo',
                'lastName' => 'Customer',
                'email' => $customer->email,
                'contactMethod' => 'email',
                'whatsapp' => null,
                'discord' => null,
            ],
            orderPayload: [
                'orderType' => 'Rank Boosting',
                'currentDivision' => 'Silver I',
                'desiredDivision' => 'Gold I',
            ],
            paymentMethod: 'cryptomus',
            priceCents: 4599,
            total: 45.99,
            subtotal: 45.99,
        );

        $store = app(PendingCheckoutStore::class);
        $pendingCheckout = $store->create($customer->id, $checkoutData);
        $pendingCheckout = $store->put($pendingCheckout->with([
            'reference' => 'CHK-CRYPTOSUCCESS',
            'metadata' => [
                'paymentProvider' => 'cryptomus',
                'paymentMethod' => 'cryptomus',
                'cryptomusInvoiceUuid' => 'cm_invoice_paid',
                'cryptomusOrderId' => 'CHK-CRYPTOSUCCESS',
            ],
        ]));

        $response = $this->actingAs($customer)->get(route('orders.success', [
            'checkout' => $pendingCheckout->token,
        ]));

        $order = Order::query()->firstOrFail();

        $response->assertRedirect(route('user-chats.show', ['order' => $order]));
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('cm_invoice_paid', $order->payment_reference);
        $this->assertNull($order->stripe_session_id);
    }

    public function test_cryptomus_webhook_creates_order_and_is_idempotent(): void
    {
        config()->set('services.cryptomus.enabled', true);
        config()->set('services.cryptomus.merchant_id', 'merchant_123');
        config()->set('services.cryptomus.api_key', 'cryptomus_api_key');

        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $checkoutData = new PaymentCheckoutData(
            requestData: [
                'firstName' => 'Webhook',
                'lastName' => 'Customer',
                'email' => $customer->email,
                'contactMethod' => 'email',
                'whatsapp' => null,
                'discord' => null,
            ],
            orderPayload: [
                'orderType' => 'Rank Boosting',
                'currentDivision' => 'Silver I',
                'desiredDivision' => 'Gold I',
            ],
            paymentMethod: 'cryptomus',
            priceCents: 3299,
            total: 32.99,
            subtotal: 32.99,
        );

        $store = app(PendingCheckoutStore::class);
        $pendingCheckout = $store->create($customer->id, $checkoutData);
        $pendingCheckout = $store->put($pendingCheckout->with([
            'reference' => 'CHK-CRYPTOWEB',
            'metadata' => [
                'paymentProvider' => 'cryptomus',
                'paymentMethod' => 'cryptomus',
                'cryptomusInvoiceUuid' => 'cm_invoice_webhook',
                'cryptomusOrderId' => 'CHK-CRYPTOWEB',
            ],
        ]));

        $payload = [
            'type' => 'payment',
            'uuid' => 'cm_invoice_webhook',
            'order_id' => 'CHK-CRYPTOWEB',
            'amount' => '32.99',
            'payment_amount' => '32.99',
            'payment_amount_usd' => '32.99',
            'merchant_amount' => '32.65',
            'commission' => '0.34',
            'is_final' => true,
            'status' => 'paid',
            'network' => 'tron',
            'currency' => 'USD',
            'payer_currency' => 'USDT',
            'txid' => 'txid_cryptomus_webhook',
            'additional_data' => $pendingCheckout->token,
        ];

        $payload['sign'] = $this->cryptomusWebhookSignature($payload, 'cryptomus_api_key');
        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        $response = $this->call(
            'POST',
            route('cryptomus.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            $jsonPayload,
        );

        $response->assertOk();
        $this->assertSame(1, Order::count());

        $order = Order::query()->firstOrFail();
        $webhookEvent = PaymentWebhookEvent::query()->firstOrFail();
        $pendingRecord = PendingCheckoutRecord::query()
            ->where('token', $pendingCheckout->token)
            ->firstOrFail();

        $this->assertSame('cm_invoice_webhook', $order->payment_reference);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(PaymentWebhookEvent::STATUS_PROCESSED, $webhookEvent->status);
        $this->assertSame($order->id, $pendingRecord->completed_order_id);

        $repeat = $this->call(
            'POST',
            route('cryptomus.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            $jsonPayload,
        );

        $repeat->assertOk();
        $this->assertSame(1, Order::count());
        $this->assertSame(1, PaymentWebhookEvent::query()->count());
    }

    public function test_cryptomus_client_rejects_disallowed_production_base_url_host(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config()->set('services.cryptomus.merchant_id', 'merchant_123');
        config()->set('services.cryptomus.api_key', 'cryptomus_api_key');
        config()->set('services.cryptomus.base_url', 'https://evil.example');
        config()->set('services.cryptomus.allowed_hosts', ['api.cryptomus.com']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cryptomus base URL host is not allowed');

        app(CryptomusClient::class)->createInvoice([]);
    }

    public function test_cryptomus_client_rejects_insecure_production_base_url_scheme(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config()->set('services.cryptomus.merchant_id', 'merchant_123');
        config()->set('services.cryptomus.api_key', 'cryptomus_api_key');
        config()->set('services.cryptomus.base_url', 'http://api.cryptomus.com');
        config()->set('services.cryptomus.allowed_hosts', ['api.cryptomus.com']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cryptomus base URL must use HTTPS');

        app(CryptomusClient::class)->createInvoice([]);
    }

    public function test_cryptomus_client_accepts_allowlisted_production_base_url(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        Http::fake([
            'https://api.cryptomus.com/v1/payment' => Http::response([
                'state' => 0,
                'result' => [
                    'uuid' => 'cm_invoice_allowed',
                    'url' => 'https://pay.cryptomus.com/pay/cm_invoice_allowed',
                ],
            ]),
        ]);
        config()->set('services.cryptomus.merchant_id', 'merchant_123');
        config()->set('services.cryptomus.api_key', 'cryptomus_api_key');
        config()->set('services.cryptomus.base_url', 'https://api.cryptomus.com');
        config()->set('services.cryptomus.allowed_hosts', ['api.cryptomus.com']);

        $result = app(CryptomusClient::class)->createInvoice([]);

        $this->assertSame('cm_invoice_allowed', $result['uuid']);
    }

    protected function cryptomusWebhookSignature(array $payload, string $apiKey): string
    {
        $data = $payload;
        unset($data['sign']);

        return md5(base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE)).$apiKey);
    }
}
