<?php

namespace Tests\Feature;

use App\Contracts\Payments\PaymentProvider;
use App\Data\Payments\PaymentCheckoutData;
use App\Data\Payments\PaymentInitializationResult;
use App\Data\Payments\PaymentProviderDescriptor;
use App\Data\Payments\PaymentVerificationResult;
use App\Data\Payments\PendingCheckout;
use App\Models\Order;
use App\Models\PendingCheckoutRecord;
use App\Models\User;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PendingCheckoutStore;
use App\Support\Pricing\ValorantPricingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckoutPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected ?PendingCheckout $capturedPendingCheckout = null;

    public function capturePendingCheckout(PendingCheckout $pendingCheckout): void
    {
        $this->capturedPendingCheckout = $pendingCheckout;
    }

    public function test_checkout_submit_does_not_create_order_before_external_payment_is_completed(): void
    {
        Http::fake();
        $this->bindPendingProvider();

        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $response = $this->actingAs($customer)->post(route('checkout.submit'), [
            'firstName' => 'Demo',
            'lastName' => 'Customer',
            'email' => $customer->email,
            'contactMethod' => 'email',
            'whatsapp' => null,
            'discord' => null,
            'paymentMethod' => 'fake-pending',
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
                'pricing' => [
                    'total' => 1.00,
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        $response->assertRedirect();
        $this->assertSame(0, Order::count());
        $location = $response->headers->get('Location', '');
        $this->assertSame('https://example.test/pay/pending', $location);
        $this->assertNotNull($this->capturedPendingCheckout);
        $this->assertSame(4129, $this->capturedPendingCheckout->priceCents);
        $this->assertSame(41.29, $this->capturedPendingCheckout->total);
    }

    public function test_checkout_submit_rejects_tampered_self_play_payloads(): void
    {
        config()->set('services.stripe.secret', 'sk_test_checkout');

        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $response = $this->actingAs($customer)
            ->from(route('home'))
            ->post(route('checkout.submit'), [
                'firstName' => 'Demo',
                'lastName' => 'Customer',
                'email' => $customer->email,
                'contactMethod' => 'email',
                'whatsapp' => null,
                'discord' => null,
                'paymentMethod' => 'stripe',
                'policy' => '1',
                'compliance' => '1',
                'orderPayload' => json_encode([
                    'serviceType' => 'Rank Boosting',
                    'currentDivision' => 'Ascendant III',
                    'desiredDivision' => 'Immortal II',
                    'currentRR' => 55,
                    'avgRRPerWin' => '18 OR LOWER',
                    'region' => 'NA',
                    'platform' => 'PC',
                    'boostMode' => 'Self-Play',
                    'selectedAddons' => ['Offline Mode', 'Express Order'],
                ], JSON_THROW_ON_ERROR),
            ]);

        $response->assertRedirect(route('home'));
        $response->assertSessionHasErrors([
            'orderPayload',
            'boostMode',
            'selectedAddons',
        ]);
        $this->assertSame(0, Order::count());
    }

    public function test_checkout_submit_requires_policy_acknowledgements_before_payment(): void
    {
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
                'paymentMethod' => 'stripe',
                'orderPayload' => json_encode([
                    'serviceType' => 'Rank Boosting',
                    'currentDivision' => 'Gold II',
                    'desiredDivision' => 'Platinum II',
                    'currentRR' => 55,
                    'avgRRPerWin' => '16 OR LOWER',
                    'region' => 'NA',
                    'platform' => 'PC',
                    'boostMode' => 'Account Shared',
                    'selectedAddons' => ['Solo-Queue Only'],
                ], JSON_THROW_ON_ERROR),
            ]);

        $response->assertRedirect(route('checkout'));
        $response->assertSessionHasErrors(['policy', 'compliance']);
        $this->assertSame(0, Order::count());
    }

    public function test_success_callback_creates_the_paid_order_after_verification(): void
    {
        Http::fake();

        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $this->app->singleton(PaymentManager::class, function () {
            return new PaymentManager([
                new class implements PaymentProvider
                {
                    public function key(): string
                    {
                        return 'fake-paid';
                    }

                    public function descriptor(): PaymentProviderDescriptor
                    {
                        return new PaymentProviderDescriptor(
                            key: 'fake-paid',
                            label: 'Fake Paid',
                            description: 'Fake provider for tests.',
                            notice: 'Paid instantly in tests.',
                            submitLabel: 'Pay',
                            isAvailable: true,
                            isDefault: true,
                        );
                    }

                    public function initialize(PendingCheckout $pendingCheckout, PaymentCheckoutData $checkoutData): PaymentInitializationResult
                    {
                        return PaymentInitializationResult::redirect('https://example.test/pay');
                    }

                    public function verify(PendingCheckout $pendingCheckout, array $payload = []): PaymentVerificationResult
                    {
                        return new PaymentVerificationResult(true, [
                            'payment_reference' => 'pi_test_paid',
                            'stripe_session_id' => 'cs_test_paid',
                        ]);
                    }
                },
            ]);
        });

        $pricingPayload = app(ValorantPricingEngine::class)->calculate([
            'serviceType' => 'Rank Boosting',
            'currentDivision' => 'Silver I',
            'desiredDivision' => 'Gold I',
            'currentRR' => 50,
            'avgRRPerWin' => '18 OR LOWER',
            'region' => 'EU',
            'platform' => 'PC',
            'boostMode' => 'Account Shared',
            'selectedAddons' => ['Express Order'],
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
            orderPayload: $pricingPayload,
            paymentMethod: 'fake-paid',
            priceCents: (int) round($pricingPayload['finalPrice'] * 100),
            total: $pricingPayload['finalPrice'],
        );

        $pendingCheckout = app(PendingCheckoutStore::class)->create($customer->id, $checkoutData);

        $response = $this->actingAs($customer)->get(route('orders.success', [
            'provider' => 'fake-paid',
            'checkout' => $pendingCheckout->token,
        ]));

        $order = Order::query()->firstOrFail();

        $response->assertRedirect(route('user-chats.show', ['order' => $order]));
        $this->assertSame(1, Order::count());
        $this->assertSame('paid', $order->payment_status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('pi_test_paid', $order->payment_reference);
        $this->assertSame('cs_test_paid', $order->stripe_session_id);
        $this->assertSame((int) round($pricingPayload['finalPrice'] * 100), $order->price_cents);

        $repeat = $this->actingAs($customer)->get(route('orders.success', [
            'provider' => 'fake-paid',
            'checkout' => $pendingCheckout->token,
        ]));

        $repeat->assertRedirect(route('user-chats.show', ['order' => $order]));
        $this->assertSame(1, Order::count());

        $pendingRecord = PendingCheckoutRecord::query()
            ->where('token', $pendingCheckout->token)
            ->firstOrFail();

        $this->assertSame($order->id, $pendingRecord->completed_order_id);
        $this->assertNotNull($pendingRecord->finalized_at);
    }

    public function test_success_callback_returns_a_json_envelope_when_json_is_requested(): void
    {
        Http::fake();

        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $this->app->singleton(PaymentManager::class, function () {
            return new PaymentManager([
                new class implements PaymentProvider
                {
                    public function key(): string
                    {
                        return 'fake-paid';
                    }

                    public function descriptor(): PaymentProviderDescriptor
                    {
                        return new PaymentProviderDescriptor(
                            key: 'fake-paid',
                            label: 'Fake Paid',
                            description: 'Fake provider for tests.',
                            notice: 'Paid instantly in tests.',
                            submitLabel: 'Pay',
                            isAvailable: true,
                            isDefault: true,
                        );
                    }

                    public function initialize(PendingCheckout $pendingCheckout, PaymentCheckoutData $checkoutData): PaymentInitializationResult
                    {
                        return PaymentInitializationResult::redirect('https://example.test/pay');
                    }

                    public function verify(PendingCheckout $pendingCheckout, array $payload = []): PaymentVerificationResult
                    {
                        return new PaymentVerificationResult(true, [
                            'payment_reference' => 'pi_test_paid_json',
                            'stripe_session_id' => 'cs_test_paid_json',
                        ]);
                    }
                },
            ]);
        });

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
                'currentDivision' => 'Gold I',
                'desiredDivision' => 'Gold II',
            ],
            paymentMethod: 'fake-paid',
            priceCents: 1999,
            total: 19.99,
            subtotal: 19.99,
        );

        $pendingCheckout = app(PendingCheckoutStore::class)->create($customer->id, $checkoutData);

        $response = $this->actingAs($customer)->getJson(route('orders.success', [
            'provider' => 'fake-paid',
            'checkout' => $pendingCheckout->token,
        ]));

        $order = Order::query()->firstOrFail();

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Payment verified successfully.',
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'redirect_url' => route('user-chats.show', ['order' => $order]),
                ],
                'errors' => null,
            ]);
    }

    public function test_guest_with_valid_checkout_token_cannot_access_success_endpoint(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $pendingCheckout = app(PendingCheckoutStore::class)->create($customer->id, new PaymentCheckoutData(
            requestData: [
                'firstName' => 'Demo',
                'lastName' => 'Customer',
                'email' => $customer->email,
                'contactMethod' => 'email',
            ],
            orderPayload: [
                'orderType' => 'Rank Boosting',
                'currentDivision' => 'Gold I',
                'desiredDivision' => 'Gold II',
            ],
            paymentMethod: 'stripe',
            priceCents: 1999,
            total: 19.99,
            subtotal: 19.99,
        ));

        $this->get(route('orders.success', [
            'checkout' => $pendingCheckout->token,
        ]))->assertRedirect(route('login'));
    }

    public function test_authenticated_wrong_user_with_valid_checkout_token_gets_forbidden(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);
        $otherUser = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $pendingCheckout = app(PendingCheckoutStore::class)->create($customer->id, new PaymentCheckoutData(
            requestData: [
                'firstName' => 'Demo',
                'lastName' => 'Customer',
                'email' => $customer->email,
                'contactMethod' => 'email',
            ],
            orderPayload: [
                'orderType' => 'Rank Boosting',
                'currentDivision' => 'Gold I',
                'desiredDivision' => 'Gold II',
            ],
            paymentMethod: 'stripe',
            priceCents: 1999,
            total: 19.99,
            subtotal: 19.99,
        ));

        $this->actingAs($otherUser)
            ->getJson(route('orders.success', [
                'checkout' => $pendingCheckout->token,
            ]))
            ->assertForbidden()
            ->assertJsonPath('message', 'You are not allowed to access this checkout.');
    }

    public function test_completed_checkout_branch_still_enforces_owner(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);
        $otherUser = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);
        $order = Order::create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-COMPLETED-OWNER',
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

        $store = app(PendingCheckoutStore::class);
        $pendingCheckout = $store->create($customer->id, new PaymentCheckoutData(
            requestData: [
                'firstName' => 'Demo',
                'lastName' => 'Customer',
                'email' => $customer->email,
                'contactMethod' => 'email',
            ],
            orderPayload: [
                'orderType' => 'Rank Boosting',
                'currentDivision' => 'Gold I',
                'desiredDivision' => 'Gold II',
            ],
            paymentMethod: 'stripe',
            priceCents: 1999,
            total: 19.99,
            subtotal: 19.99,
        ));
        $pendingCheckout = $store->markCompleted($pendingCheckout, $order);

        $this->actingAs($otherUser)
            ->getJson(route('orders.success', [
                'checkout' => $pendingCheckout->token,
            ]))
            ->assertForbidden();

        $this->actingAs($customer)
            ->get(route('orders.success', [
                'checkout' => $pendingCheckout->token,
            ]))
            ->assertRedirect(route('user-chats.show', ['order' => $order]));
    }

    public function test_zero_dollar_checkout_creates_an_internal_free_order(): void
    {
        Http::fake();
        config()->set('services.stripe.secret', null);

        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $promoCode = \App\Models\PromoCode::factory()->create([
            'code' => 'FREEBOOST',
            'type' => \App\Models\PromoCode::TYPE_FIXED,
            'value' => 999,
        ]);

        $response = $this->actingAs($customer)->post(route('checkout.submit'), [
            'firstName' => 'Demo',
            'lastName' => 'Customer',
            'email' => $customer->email,
            'contactMethod' => 'email',
            'whatsapp' => null,
            'discord' => null,
            'promoCode' => $promoCode->code,
            'paymentMethod' => 'stripe',
            'policy' => '1',
            'compliance' => '1',
            'orderPayload' => json_encode([
                'serviceType' => 'Rank Boosting',
                'currentDivision' => 'Gold II',
                'desiredDivision' => 'Gold III',
                'currentRR' => 55,
                'avgRRPerWin' => '16 OR LOWER',
                'region' => 'NA',
                'platform' => 'PC',
                'boostMode' => 'Account Shared',
                'selectedAddons' => [],
            ], JSON_THROW_ON_ERROR),
        ]);

        $order = Order::query()->firstOrFail();

        $response->assertRedirect(route('user-chats.show', ['order' => $order]));
        $this->assertSame(1, Order::count());
        $this->assertSame(0, $order->price_cents);
        $this->assertSame('paid', $order->payment_status);
        $this->assertNull($order->stripe_session_id);
        $this->assertStringStartsWith('free:CHK-', $order->payment_reference);
        $this->assertNotNull($order->paid_at);
    }

    public function test_paid_checkout_returns_a_validation_error_when_stripe_is_misconfigured(): void
    {
        Http::fake();
        config()->set('services.stripe.secret', null);

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
                'paymentMethod' => 'stripe',
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

        $response->assertRedirect(route('checkout'));
        $response->assertSessionHasErrors('payment');
        $this->assertSame(0, Order::count());
    }

    public function test_expired_pending_checkout_cannot_be_finalized_on_the_success_page_and_can_be_pruned(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $checkoutData = new PaymentCheckoutData(
            requestData: [
                'firstName' => 'Expired',
                'lastName' => 'Customer',
                'email' => $customer->email,
                'contactMethod' => 'email',
                'whatsapp' => null,
                'discord' => null,
            ],
            orderPayload: [
                'orderType' => 'Rank Boosting',
                'currentDivision' => 'Gold I',
                'desiredDivision' => 'Gold II',
            ],
            paymentMethod: 'stripe',
            priceCents: 1999,
            total: 19.99,
            subtotal: 19.99,
        );

        $pendingCheckout = app(PendingCheckoutStore::class)->create($customer->id, $checkoutData);

        PendingCheckoutRecord::query()
            ->where('token', $pendingCheckout->token)
            ->update([
                'expires_at' => now()->subHours((int) config('payments.pending_checkouts.stale_retention_hours', 168) + 1),
            ]);

        $response = $this->actingAs($customer)->get(route('orders.success', [
            'provider' => 'stripe',
            'checkout' => $pendingCheckout->token,
        ]));

        $response->assertRedirect(route('checkout'));
        $response->assertSessionHasErrors('payment');
        $this->assertSame(0, Order::count());

        $this->artisan('pending-checkouts:prune')->assertSuccessful();

        $this->assertDatabaseMissing('pending_checkouts', [
            'token' => $pendingCheckout->token,
        ]);
    }

    public function test_expired_pending_checkout_returns_a_json_error_envelope(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $checkoutData = new PaymentCheckoutData(
            requestData: [
                'firstName' => 'Expired',
                'lastName' => 'Customer',
                'email' => $customer->email,
                'contactMethod' => 'email',
                'whatsapp' => null,
                'discord' => null,
            ],
            orderPayload: [
                'orderType' => 'Rank Boosting',
                'currentDivision' => 'Gold I',
                'desiredDivision' => 'Gold II',
            ],
            paymentMethod: 'stripe',
            priceCents: 1999,
            total: 19.99,
            subtotal: 19.99,
        );

        $pendingCheckout = app(PendingCheckoutStore::class)->create($customer->id, $checkoutData);

        PendingCheckoutRecord::query()
            ->where('token', $pendingCheckout->token)
            ->update([
                'expires_at' => now()->subHours((int) config('payments.pending_checkouts.stale_retention_hours', 168) + 1),
            ]);

        $response = $this->actingAs($customer)->getJson(route('orders.success', [
            'provider' => 'stripe',
            'checkout' => $pendingCheckout->token,
        ]));

        $response
            ->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Your payment session expired. Please start checkout again.',
                'data' => null,
                'errors' => [
                    'payment' => ['Your payment session expired. Please start checkout again.'],
                ],
            ]);
    }

    public function test_success_callback_does_not_reveal_existing_order_by_session_id_to_guests(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $order = Order::create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-SESSION-FALLBACK',
            'product' => 'Rank Boosting',
            'status' => 'Pending',
            'payment_status' => 'paid',
            'price_cents' => 1999,
            'currency' => 'USD',
            'details' => ['order' => ['orderType' => 'Rank Boosting']],
            'metadata' => [],
            'contact_method' => 'email',
            'is_custom' => false,
            'stripe_session_id' => 'cs_test_secret_fallback',
        ]);

        $this->getJson(route('orders.success', [
            'session_id' => $order->stripe_session_id,
        ]))
            ->assertUnauthorized()
            ->assertJsonPath('data', null);

        $this->actingAs($customer)
            ->getJson(route('orders.success', [
                'session_id' => $order->stripe_session_id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.order_id', $order->id);
    }

    public function test_checkout_submit_rejects_ranked_wins_above_the_public_limit(): void
    {
        config()->set('services.stripe.secret', 'sk_test_checkout');

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
                'paymentMethod' => 'stripe',
                'policy' => '1',
                'compliance' => '1',
                'orderPayload' => json_encode([
                    'serviceType' => 'Ranked Wins',
                    'currentDivision' => 'Diamond I',
                    'numberOfWins' => 6,
                    'region' => 'APAC',
                    'platform' => 'Console',
                    'boostMode' => 'Account Shared',
                    'selectedAddons' => [],
                ], JSON_THROW_ON_ERROR),
            ]);

        $response->assertRedirect(route('checkout'));
        $response->assertSessionHasErrors(['orderPayload', 'numberOfWins']);
        $this->assertSame(0, Order::count());
    }

    public function test_checkout_submit_rejects_tampered_specific_agent_payloads(): void
    {
        config()->set('services.stripe.secret', 'sk_test_checkout');

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
                'paymentMethod' => 'stripe',
                'policy' => '1',
                'compliance' => '1',
                'orderPayload' => json_encode([
                    'serviceType' => 'Rank Boosting',
                    'currentDivision' => 'Gold II',
                    'desiredDivision' => 'Platinum II',
                    'currentRR' => 55,
                    'avgRRPerWin' => '18 OR LOWER',
                    'region' => 'NA',
                    'platform' => 'PC',
                    'boostMode' => 'Account Shared',
                    'selectedAddons' => ['Offline Mode'],
                    'specificAgents' => [config('valorant_agents.0.uuid')],
                ], JSON_THROW_ON_ERROR),
            ]);

        $response->assertRedirect(route('checkout'));
        $response->assertSessionHasErrors(['orderPayload', 'specificAgents']);
        $this->assertSame(0, Order::count());
    }

    public function test_checkout_submit_rejects_specific_agents_below_the_minimum_selection(): void
    {
        config()->set('services.stripe.secret', 'sk_test_checkout');

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
                'paymentMethod' => 'stripe',
                'policy' => '1',
                'compliance' => '1',
                'orderPayload' => json_encode([
                    'serviceType' => 'Rank Boosting',
                    'currentDivision' => 'Gold II',
                    'desiredDivision' => 'Platinum II',
                    'currentRR' => 55,
                    'avgRRPerWin' => '18 OR LOWER',
                    'region' => 'NA',
                    'platform' => 'PC',
                    'boostMode' => 'Account Shared',
                    'selectedAddons' => ['Specific Agents'],
                    'specificAgents' => $this->agentUuids(2),
                ], JSON_THROW_ON_ERROR),
            ]);

        $response->assertRedirect(route('checkout'));
        $response->assertSessionHasErrors(['orderPayload', 'specificAgents']);
        $this->assertSame(0, Order::count());
    }

    public function test_zero_dollar_checkout_persists_one_trick_agent_selection(): void
    {
        Http::fake();
        config()->set('services.stripe.secret', null);

        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $promoCode = \App\Models\PromoCode::factory()->create([
            'code' => 'FREEONETRICK',
            'type' => \App\Models\PromoCode::TYPE_FIXED,
            'value' => 999,
        ]);

        $agentUuid = $this->agentUuids(1);

        $response = $this->actingAs($customer)->post(route('checkout.submit'), [
            'firstName' => 'Demo',
            'lastName' => 'Customer',
            'email' => $customer->email,
            'contactMethod' => 'email',
            'whatsapp' => null,
            'discord' => null,
            'promoCode' => $promoCode->code,
            'paymentMethod' => 'stripe',
            'policy' => '1',
            'compliance' => '1',
            'orderPayload' => json_encode([
                'serviceType' => 'Rank Boosting',
                'currentDivision' => 'Gold II',
                'desiredDivision' => 'Gold III',
                'currentRR' => 55,
                'avgRRPerWin' => '16 OR LOWER',
                'region' => 'NA',
                'platform' => 'PC',
                'boostMode' => 'Account Shared',
                'selectedAddons' => ['One-Trick Agent'],
                'oneTrickAgent' => $agentUuid,
            ], JSON_THROW_ON_ERROR),
        ]);

        $order = Order::query()->firstOrFail();

        $response->assertRedirect(route('user-chats.show', ['order' => $order]));
        $this->assertSame($agentUuid, data_get($order->details, 'oneTrickAgent'));
        $this->assertSame($agentUuid, data_get($order->details, 'order.oneTrickAgent'));
    }

    public function test_checkout_submit_rejects_invalid_one_trick_agent_payloads(): void
    {
        config()->set('services.stripe.secret', 'sk_test_checkout');

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
                'paymentMethod' => 'stripe',
                'policy' => '1',
                'compliance' => '1',
                'orderPayload' => json_encode([
                    'serviceType' => 'Rank Boosting',
                    'currentDivision' => 'Gold II',
                    'desiredDivision' => 'Platinum II',
                    'currentRR' => 55,
                    'avgRRPerWin' => '18 OR LOWER',
                    'region' => 'NA',
                    'platform' => 'PC',
                    'boostMode' => 'Account Shared',
                    'selectedAddons' => ['One-Trick Agent'],
                    'oneTrickAgent' => $this->agentUuids(2),
                ], JSON_THROW_ON_ERROR),
            ]);

        $response->assertRedirect(route('checkout'));
        $response->assertSessionHasErrors(['orderPayload', 'oneTrickAgent']);
        $this->assertSame(0, Order::count());
    }

    protected function agentUuids(int $count): array
    {
        return collect(config('valorant_agents', []))
            ->pluck('uuid')
            ->filter()
            ->take($count)
            ->values()
            ->all();
    }

    protected function bindPendingProvider(): void
    {
        $testCase = $this;

        $this->app->singleton(PaymentManager::class, function () use ($testCase) {
            return new PaymentManager([
                new class($testCase) implements PaymentProvider
                {
                    public function __construct(protected CheckoutPaymentFlowTest $testCase) {}

                    public function key(): string
                    {
                        return 'fake-pending';
                    }

                    public function descriptor(): PaymentProviderDescriptor
                    {
                        return new PaymentProviderDescriptor(
                            key: 'fake-pending',
                            label: 'Fake Pending',
                            description: 'Fake provider for tests.',
                            notice: 'Payment remains pending in tests.',
                            submitLabel: 'Continue',
                            isAvailable: true,
                            isDefault: true,
                        );
                    }

                    public function initialize(PendingCheckout $pendingCheckout, PaymentCheckoutData $checkoutData): PaymentInitializationResult
                    {
                        $this->testCase->capturePendingCheckout($pendingCheckout);

                        return PaymentInitializationResult::redirect('https://example.test/pay/pending');
                    }

                    public function verify(PendingCheckout $pendingCheckout, array $payload = []): PaymentVerificationResult
                    {
                        return new PaymentVerificationResult(false);
                    }
                },
            ]);
        });
    }
}
