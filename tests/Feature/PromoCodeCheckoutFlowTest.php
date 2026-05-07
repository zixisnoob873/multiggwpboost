<?php

namespace Tests\Feature;

use App\Contracts\Payments\PaymentProvider;
use App\Data\Payments\PaymentCheckoutData;
use App\Data\Payments\PaymentInitializationResult;
use App\Data\Payments\PaymentProviderDescriptor;
use App\Data\Payments\PaymentVerificationResult;
use App\Data\Payments\PendingCheckout;
use App\Models\Order;
use App\Models\PromoCode;
use App\Models\User;
use App\Queries\AdminDashboardQuery;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PendingCheckoutStore;
use App\Services\PromoCodeService;
use App\Support\Pricing\ValorantPricingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PromoCodeCheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_promo_code_preview_returns_discount_without_consuming_usage(): void
    {
        $promoCode = PromoCode::factory()->create([
            'code' => 'BOOST10',
            'type' => PromoCode::TYPE_PERCENTAGE,
            'value' => 10,
        ]);

        $orderPayload = [
            'serviceType' => 'Rank Boosting',
            'currentDivision' => 'Gold II',
            'desiredDivision' => 'Platinum II',
            'currentRR' => 55,
            'avgRRPerWin' => '16 OR LOWER',
            'region' => 'NA',
            'platform' => 'PC',
            'boostMode' => 'Account Shared',
            'selectedAddons' => ['Solo-Queue Only', 'Offline Mode'],
        ];

        $response = $this->postJson(route('checkout.promo.preview'), [
            'promoCode' => $promoCode->code,
            'orderPayload' => json_encode($orderPayload, JSON_THROW_ON_ERROR),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('promo.code', 'BOOST10')
            ->assertJsonPath('promo.discountAmount', 4.13)
            ->assertJsonPath('pricing.finalTotal', 37.16);

        $this->assertSame(0, $promoCode->fresh()->used_count);
    }

    public function test_addon_promo_preview_returns_server_resolved_addons_and_adjusted_totals(): void
    {
        $promoCode = PromoCode::factory()->addonPromo()->create([
            'code' => 'ADDONFREE',
        ]);
        $promoCode->addonRules()->createMany([
            [
                'addon_slug' => 'streaming',
                'discount_type' => PromoCode::ADDON_DISCOUNT_TYPE_FREE,
                'discount_value' => 0,
            ],
            [
                'addon_slug' => 'solo-queue-only',
                'discount_type' => PromoCode::ADDON_DISCOUNT_TYPE_PERCENTAGE,
                'discount_value' => 25,
            ],
        ]);

        $orderPayload = [
            'serviceType' => 'Rank Boosting',
            'currentDivision' => 'Gold II',
            'desiredDivision' => 'Platinum II',
            'currentRR' => 55,
            'avgRRPerWin' => '16 OR LOWER',
            'region' => 'NA',
            'platform' => 'PC',
            'boostMode' => 'Account Shared',
            'selectedAddons' => [],
        ];

        $response = $this->postJson(route('checkout.promo.preview'), [
            'promoCode' => $promoCode->code,
            'orderPayload' => json_encode($orderPayload, JSON_THROW_ON_ERROR),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('promo.code', 'ADDONFREE');

        $this->assertEqualsCanonicalizing(
            ['Streaming', 'Solo-Queue Only'],
            $response->json('promo.promoManagedAddons')
        );
        $this->assertEqualsCanonicalizing(
            ['Streaming', 'Solo-Queue Only'],
            $response->json('order.addons')
        );
        $this->assertEqualsCanonicalizing(
            ['Streaming', 'Solo-Queue Only'],
            $response->json('order.promoAddedAddons')
        );

        $this->assertGreaterThan(
            $response->json('pricing.finalTotal'),
            $response->json('pricing.originalTotal')
        );
    }

    public function test_checkout_submit_does_not_consume_promo_code_before_payment_success(): void
    {
        Http::fake();
        $this->bindFakePendingProvider();

        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $promoCode = PromoCode::factory()->create([
            'code' => 'BOOST5',
            'type' => PromoCode::TYPE_FIXED,
            'value' => 5,
        ]);

        $response = $this->actingAs($customer)->post(route('checkout.submit'), [
            'firstName' => 'Demo',
            'lastName' => 'Customer',
            'email' => $customer->email,
            'contactMethod' => 'email',
            'whatsapp' => null,
            'discord' => null,
            'promoCode' => $promoCode->code,
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
            ], JSON_THROW_ON_ERROR),
        ]);

        $response->assertRedirect();
        $this->assertSame(0, Order::count());
        $this->assertSame(0, $promoCode->fresh()->used_count);
    }

    public function test_payment_success_creates_order_and_consumes_promo_code_once(): void
    {
        Http::fake();
        $this->bindFakePaidProvider();

        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $promoCode = PromoCode::factory()->create([
            'code' => 'BOOST10',
            'type' => PromoCode::TYPE_PERCENTAGE,
            'value' => 10,
            'max_uses' => 1,
        ]);

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

        $promoResult = app(PromoCodeService::class)->validateCode($promoCode->code, (float) $pricingPayload['finalPrice']);

        $checkoutData = new PaymentCheckoutData(
            requestData: [
                'firstName' => 'Demo',
                'lastName' => 'Customer',
                'email' => $customer->email,
                'contactMethod' => 'email',
                'whatsapp' => null,
                'discord' => null,
                'promoCode' => $promoCode->code,
            ],
            orderPayload: $pricingPayload,
            paymentMethod: 'fake-paid',
            priceCents: (int) round($promoResult->discountedTotal * 100),
            total: $promoResult->discountedTotal,
            subtotal: (float) $pricingPayload['finalPrice'],
            promoCodeId: $promoCode->id,
            promoCode: $promoCode->code,
            discountAmount: $promoResult->discountAmount,
        );

        $pendingCheckout = app(PendingCheckoutStore::class)->create($customer->id, $checkoutData);

        $response = $this->actingAs($customer)->get(route('orders.success', [
            'provider' => 'fake-paid',
            'checkout' => $pendingCheckout->token,
        ]));

        $order = Order::query()->firstOrFail();

        $response->assertRedirect(route('user-chats.show', ['order' => $order]));
        $this->assertSame($promoCode->id, $order->promo_code_id);
        $this->assertSame(number_format($promoResult->discountAmount, 2, '.', ''), (string) $order->discount_amount);
        $this->assertSame((int) round(((float) $pricingPayload['finalPrice']) * 100), $order->original_price_cents);
        $this->assertSame((int) round($promoResult->discountedTotal * 100), $order->price_cents);
        $this->assertSame($order->original_price_cents, $order->booster_payout_basis_cents);
        $this->assertSame(
            (int) round($order->original_price_cents * Order::configuredBoosterPayoutRate()),
            $order->booster_payout_cents
        );
        $this->assertSame(1, $promoCode->fresh()->used_count);

        $repeat = $this->actingAs($customer)->get(route('orders.success', [
            'provider' => 'fake-paid',
            'checkout' => $pendingCheckout->token,
        ]));

        $repeat->assertRedirect(route('user-chats.show', ['order' => $order]));
        $this->assertSame(1, Order::count());
        $this->assertSame(1, $promoCode->fresh()->used_count);
    }

    public function test_payment_success_fails_cleanly_if_promo_code_becomes_unavailable_before_consumption(): void
    {
        Http::fake();
        $this->bindFakePaidProvider();

        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $promoCode = PromoCode::factory()->create([
            'code' => 'LASTCALL',
            'type' => PromoCode::TYPE_FIXED,
            'value' => 5,
            'max_uses' => 1,
            'used_count' => 0,
        ]);

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

        $promoResult = app(PromoCodeService::class)->validateCode($promoCode->code, (float) $pricingPayload['finalPrice']);

        $checkoutData = new PaymentCheckoutData(
            requestData: [
                'firstName' => 'Demo',
                'lastName' => 'Customer',
                'email' => $customer->email,
                'contactMethod' => 'email',
                'whatsapp' => null,
                'discord' => null,
                'promoCode' => $promoCode->code,
            ],
            orderPayload: $pricingPayload,
            paymentMethod: 'fake-paid',
            priceCents: (int) round($promoResult->discountedTotal * 100),
            total: $promoResult->discountedTotal,
            subtotal: (float) $pricingPayload['finalPrice'],
            promoCodeId: $promoCode->id,
            promoCode: $promoCode->code,
            discountAmount: $promoResult->discountAmount,
        );

        $pendingCheckout = app(PendingCheckoutStore::class)->create($customer->id, $checkoutData);
        $promoCode->update(['used_count' => 1]);

        $response = $this->actingAs($customer)->get(route('orders.success', [
            'provider' => 'fake-paid',
            'checkout' => $pendingCheckout->token,
        ]));

        $response->assertRedirect(route('checkout'));
        $response->assertSessionHasErrors(['promoCode']);
        $this->assertSame(0, Order::count());
        $this->assertSame(1, $promoCode->fresh()->used_count);
    }

    public function test_admin_finance_keeps_promo_discount_and_booster_payout_basis_separate(): void
    {
        Http::fake();
        $this->bindFakePaidProvider();

        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $promoCode = PromoCode::factory()->create([
            'code' => 'BASIS20',
            'type' => PromoCode::TYPE_PERCENTAGE,
            'value' => 20,
        ]);

        $pricingPayload = app(ValorantPricingEngine::class)->calculate([
            'serviceType' => 'Rank Boosting',
            'currentDivision' => 'Silver II',
            'desiredDivision' => 'Gold II',
            'currentRR' => 35,
            'avgRRPerWin' => '18 OR LOWER',
            'region' => 'NA',
            'platform' => 'PC',
            'boostMode' => 'Account Shared',
            'selectedAddons' => ['Express Order'],
        ]);

        $promoResult = app(PromoCodeService::class)->validateCode($promoCode->code, (float) $pricingPayload['finalPrice']);
        $checkoutData = new PaymentCheckoutData(
            requestData: [
                'firstName' => 'Promo',
                'lastName' => 'Customer',
                'email' => $customer->email,
                'contactMethod' => 'email',
                'whatsapp' => null,
                'discord' => null,
                'promoCode' => $promoCode->code,
            ],
            orderPayload: $pricingPayload,
            paymentMethod: 'fake-paid',
            priceCents: (int) round($promoResult->discountedTotal * 100),
            total: $promoResult->discountedTotal,
            subtotal: (float) $pricingPayload['finalPrice'],
            promoCodeId: $promoCode->id,
            promoCode: $promoCode->code,
            discountAmount: $promoResult->discountAmount,
        );

        $pendingCheckout = app(PendingCheckoutStore::class)->create($customer->id, $checkoutData);

        $this->actingAs($customer)->get(route('orders.success', [
            'provider' => 'fake-paid',
            'checkout' => $pendingCheckout->token,
        ]))->assertRedirect();

        $order = Order::query()->firstOrFail();
        $dashboard = app(AdminDashboardQuery::class)->execute('all_time');

        $this->assertSame($order->customerPriceCents(), $dashboard['totalSaleCents']);
        $this->assertSame($order->resolvedOriginalPriceCents(), $dashboard['totalOriginalSaleCents']);
        $this->assertSame($order->resolvedDiscountAmountCents(), $dashboard['totalDiscountCents']);
        $this->assertSame($order->resolvedBoosterPayoutCents(), $dashboard['estimatedBoosterPayoutsCents']);
    }

    public function test_addon_promo_payment_success_persists_added_addons_and_pricing_basis(): void
    {
        Http::fake();
        $this->bindFakePaidProvider();

        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $promoCode = PromoCode::factory()->addonPromo()->create([
            'code' => 'ADDON25',
            'max_uses' => 1,
        ]);
        $promoCode->addonRules()->createMany([
            [
                'addon_slug' => 'streaming',
                'discount_type' => PromoCode::ADDON_DISCOUNT_TYPE_FREE,
                'discount_value' => 0,
            ],
            [
                'addon_slug' => 'solo-queue-only',
                'discount_type' => PromoCode::ADDON_DISCOUNT_TYPE_PERCENTAGE,
                'discount_value' => 25,
            ],
        ]);

        $basePayload = app(ValorantPricingEngine::class)->calculate([
            'serviceType' => 'Rank Boosting',
            'currentDivision' => 'Silver I',
            'desiredDivision' => 'Gold I',
            'currentRR' => 50,
            'avgRRPerWin' => '18 OR LOWER',
            'region' => 'EU',
            'platform' => 'PC',
            'boostMode' => 'Account Shared',
            'selectedAddons' => [],
        ]);

        $promoResult = app(PromoCodeService::class)->resolveCodeForPayload($promoCode->code, $basePayload);

        $checkoutData = new PaymentCheckoutData(
            requestData: [
                'firstName' => 'Addon',
                'lastName' => 'Customer',
                'email' => $customer->email,
                'contactMethod' => 'email',
                'whatsapp' => null,
                'discord' => null,
                'promoCode' => $promoCode->code,
            ],
            orderPayload: $promoResult->originalOrderPayload,
            paymentMethod: 'fake-paid',
            priceCents: (int) round($promoResult->discountedTotal * 100),
            total: $promoResult->discountedTotal,
            subtotal: $promoResult->orderAmount,
            promoCodeId: $promoCode->id,
            promoCode: $promoCode->code,
            discountAmount: $promoResult->discountAmount,
            baseOrderPayload: $basePayload,
        );

        $pendingCheckout = app(PendingCheckoutStore::class)->create($customer->id, $checkoutData);

        $this->actingAs($customer)->get(route('orders.success', [
            'provider' => 'fake-paid',
            'checkout' => $pendingCheckout->token,
        ]))->assertRedirect();

        $order = Order::query()->firstOrFail();

        $this->assertSame($promoCode->id, $order->promo_code_id);
        $this->assertSame((int) round($promoResult->orderAmount * 100), $order->original_price_cents);
        $this->assertSame((int) round($promoResult->discountedTotal * 100), $order->price_cents);
        $this->assertSame(number_format($promoResult->discountAmount, 2, '.', ''), (string) $order->discount_amount);
        $this->assertEqualsCanonicalizing(['Streaming', 'Solo-Queue Only'], $order->details['addons'] ?? []);
        $this->assertEqualsCanonicalizing(['Streaming', 'Solo-Queue Only'], data_get($order->metadata, 'promoCode.addedAddons'));
        $this->assertSame(1, $promoCode->fresh()->used_count);
    }

    protected function bindFakePaidProvider(): void
    {
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
    }

    protected function bindFakePendingProvider(): void
    {
        $this->app->singleton(PaymentManager::class, function () {
            return new PaymentManager([
                new class implements PaymentProvider
                {
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
                            notice: 'Pending in tests.',
                            submitLabel: 'Continue',
                            isAvailable: true,
                            isDefault: true,
                        );
                    }

                    public function initialize(PendingCheckout $pendingCheckout, PaymentCheckoutData $checkoutData): PaymentInitializationResult
                    {
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
