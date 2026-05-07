<?php

namespace Tests\Feature;

use App\Contracts\Payments\PaymentProvider;
use App\Data\Payments\PaymentCheckoutData;
use App\Data\Payments\PaymentInitializationResult;
use App\Data\Payments\PaymentProviderDescriptor;
use App\Data\Payments\PaymentVerificationResult;
use App\Data\Payments\PendingCheckout;
use App\Models\Order;
use App\Models\PaymentWebhookEvent;
use App\Models\PendingCheckoutRecord;
use App\Models\User;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PendingCheckoutStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_stripe_webhook_creates_order_and_is_idempotent(): void
    {
        Http::fake();

        config()->set('services.stripe.webhook_secret', 'stripe_webhook_test_secret');

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
            paymentMethod: 'stripe',
            priceCents: 3299,
            total: 32.99,
            subtotal: 32.99,
        );

        $pendingCheckout = app(PendingCheckoutStore::class)->create($customer->id, $checkoutData);

        $payload = json_encode([
            'id' => 'evt_test_checkout_completed',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_webhook',
                    'object' => 'checkout.session',
                    'payment_status' => 'paid',
                    'amount_total' => 3299,
                    'payment_intent' => 'pi_test_webhook',
                    'metadata' => [
                        'checkout_token' => $pendingCheckout->token,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $signature = $this->stripeSignature($payload, config('services.stripe.webhook_secret'));

        $response = $this->call(
            'POST',
            route('stripe.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Stripe_Signature' => $signature,
            ],
            $payload,
        );

        $response->assertOk();
        $this->assertSame(1, Order::count());

        $order = Order::query()->firstOrFail();
        $webhookEvent = PaymentWebhookEvent::query()->firstOrFail();
        $pendingRecord = PendingCheckoutRecord::query()
            ->where('token', $pendingCheckout->token)
            ->firstOrFail();

        $this->assertSame('cs_test_webhook', $order->stripe_session_id);
        $this->assertSame('pi_test_webhook', $order->payment_reference);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(PaymentWebhookEvent::STATUS_PROCESSED, $webhookEvent->status);
        $this->assertSame(1, $webhookEvent->attempts);
        $this->assertSame($order->id, $webhookEvent->order_id);
        $this->assertSame($pendingRecord->id, $webhookEvent->pending_checkout_id);
        $this->assertSame($order->id, $pendingRecord->completed_order_id);
        $this->assertNotNull($pendingRecord->finalized_at);
        $rawPayload = (string) DB::table('payment_webhook_events')
            ->where('id', $webhookEvent->id)
            ->value('payload');
        $this->assertStringNotContainsString('evt_test_checkout_completed', $rawPayload);
        $this->assertSame('evt_test_checkout_completed', $webhookEvent->payload['id']);

        $repeat = $this->call(
            'POST',
            route('stripe.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Stripe_Signature' => $signature,
            ],
            $payload,
        );

        $repeat->assertOk();
        $this->assertSame(1, Order::count());
        $this->assertSame(1, PaymentWebhookEvent::query()->count());
        $this->assertSame(1, PaymentWebhookEvent::query()->firstOrFail()->attempts);
    }

    public function test_success_page_and_webhook_converge_on_a_single_order(): void
    {
        Http::fake();

        config()->set('services.stripe.webhook_secret', 'stripe_webhook_test_secret');
        $this->bindFakeStripeProvider('cs_test_shared', 'pi_test_shared');

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
            paymentMethod: 'stripe',
            priceCents: 3299,
            total: 32.99,
            subtotal: 32.99,
        );

        $pendingCheckout = app(PendingCheckoutStore::class)->create($customer->id, $checkoutData);

        $success = $this->actingAs($customer)->get(route('orders.success', [
            'checkout' => $pendingCheckout->token,
            'session_id' => 'cs_test_shared',
        ]));

        $order = Order::query()->firstOrFail();

        $success->assertRedirect(route('user-chats.show', ['order' => $order]));
        $this->assertSame(1, Order::count());
        $this->assertSame('cs_test_shared', $order->stripe_session_id);
        $this->assertSame('pi_test_shared', $order->payment_reference);

        $payload = json_encode([
            'id' => 'evt_test_checkout_shared',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_shared',
                    'object' => 'checkout.session',
                    'payment_status' => 'paid',
                    'amount_total' => 3299,
                    'payment_intent' => 'pi_test_shared',
                    'metadata' => [
                        'checkout_token' => $pendingCheckout->token,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $signature = $this->stripeSignature($payload, config('services.stripe.webhook_secret'));

        $webhook = $this->call(
            'POST',
            route('stripe.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Stripe_Signature' => $signature,
            ],
            $payload,
        );

        $webhook->assertOk();
        $this->assertSame(1, Order::count());
        $this->assertSame($order->id, Order::query()->firstOrFail()->id);
    }

    public function test_webhook_can_finalize_from_durable_pending_checkout_storage_even_if_cache_is_flushed(): void
    {
        Http::fake();

        config()->set('services.stripe.webhook_secret', 'stripe_webhook_test_secret');

        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $checkoutData = new PaymentCheckoutData(
            requestData: [
                'firstName' => 'Webhook',
                'lastName' => 'Only',
                'email' => $customer->email,
                'contactMethod' => 'email',
                'whatsapp' => null,
                'discord' => null,
            ],
            orderPayload: [
                'orderType' => 'Placement Games',
                'currentDivision' => 'Unranked',
                'desiredDivision' => 'Gold I',
            ],
            paymentMethod: 'stripe',
            priceCents: 4599,
            total: 45.99,
            subtotal: 45.99,
        );

        $pendingCheckout = app(PendingCheckoutStore::class)->create($customer->id, $checkoutData);

        Cache::flush();

        $payload = json_encode([
            'id' => 'evt_test_webhook_only',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_webhook_only',
                    'object' => 'checkout.session',
                    'payment_status' => 'paid',
                    'amount_total' => 4599,
                    'payment_intent' => 'pi_test_webhook_only',
                    'metadata' => [
                        'checkout_token' => $pendingCheckout->token,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $signature = $this->stripeSignature($payload, config('services.stripe.webhook_secret'));

        $response = $this->call(
            'POST',
            route('stripe.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Stripe_Signature' => $signature,
            ],
            $payload,
        );

        $response->assertOk();

        $order = Order::query()->firstOrFail();
        $pendingRecord = PendingCheckoutRecord::query()->where('token', $pendingCheckout->token)->firstOrFail();

        $this->assertSame('cs_test_webhook_only', $order->stripe_session_id);
        $this->assertSame('pi_test_webhook_only', $order->payment_reference);
        $this->assertSame($order->id, $pendingRecord->completed_order_id);
    }

    protected function stripeSignature(string $payload, string $secret): string
    {
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, $secret);

        return "t={$timestamp},v1={$signature}";
    }

    protected function bindFakeStripeProvider(string $sessionId, string $paymentReference): void
    {
        $this->app->singleton(PaymentManager::class, function () use ($sessionId, $paymentReference) {
            return new PaymentManager([
                new class($sessionId, $paymentReference) implements PaymentProvider
                {
                    public function __construct(
                        protected string $sessionId,
                        protected string $paymentReference,
                    ) {}

                    public function key(): string
                    {
                        return 'stripe';
                    }

                    public function descriptor(): PaymentProviderDescriptor
                    {
                        return new PaymentProviderDescriptor(
                            key: 'stripe',
                            label: 'Stripe',
                            description: 'Fake Stripe for tests.',
                            notice: 'Paid in tests.',
                            submitLabel: 'Pay',
                            isAvailable: true,
                            isDefault: true,
                        );
                    }

                    public function initialize(PendingCheckout $pendingCheckout, PaymentCheckoutData $checkoutData): PaymentInitializationResult
                    {
                        return PaymentInitializationResult::redirect('https://example.test/stripe');
                    }

                    public function verify(PendingCheckout $pendingCheckout, array $payload = []): PaymentVerificationResult
                    {
                        return new PaymentVerificationResult(true, [
                            'payment_status' => 'paid',
                            'payment_reference' => $this->paymentReference,
                            'stripe_session_id' => $this->sessionId,
                        ]);
                    }
                },
            ]);
        });
    }
}
