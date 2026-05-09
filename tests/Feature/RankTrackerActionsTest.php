<?php

namespace Tests\Feature;

use App\Contracts\Payments\PaymentProvider;
use App\Data\Payments\PaymentCheckoutData;
use App\Data\Payments\PaymentInitializationResult;
use App\Data\Payments\PaymentProviderDescriptor;
use App\Data\Payments\PaymentVerificationResult;
use App\Data\Payments\PendingCheckout;
use App\Enums\OrderChatThreadType;
use App\Models\Order;
use App\Models\OrderChatMessage;
use App\Models\OrderExtension;
use App\Models\OrderTip;
use App\Models\PendingCheckoutRecord;
use App\Models\User;
use App\Queries\AdminDashboardQuery;
use App\Services\BoosterWalletService;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PendingCheckoutStore;
use App\Support\OrderStatus;
use App\Support\Pricing\ValorantPricingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class RankTrackerActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bindFakePaidProvider();
    }

    public function test_user_chat_page_contains_real_rank_tracker_action_forms(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makePricedOrder($customer, $booster, 'Rank Boosting');

        $this->actingAs($customer)
            ->get(route('user-chats.show', ['order' => $order]))
            ->assertOk()
            ->assertSee('extendBoostModal', false)
            ->assertSee(route('customer-orders.extend.checkout', ['order' => $order]), false)
            ->assertSee(route('customer-orders.pause', ['order' => $order]), false)
            ->assertSee(route('customer-orders.tips.booster.checkout', ['order' => $order]), false)
            ->assertSee(route('customer-orders.tips.admin.checkout', ['order' => $order]), false);
    }

    public function test_extension_modal_shows_only_the_correct_options_for_each_service_type(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');

        $rankBoostOrder = $this->makePricedOrder($customer, $booster, 'Rank Boosting');
        $this->actingAs($customer)
            ->get(route('user-chats.show', ['order' => $rankBoostOrder]))
            ->assertOk()
            ->assertSee('Extend Rank Boost')
            ->assertSee('New target rank')
            ->assertDontSee('Additional wins')
            ->assertDontSee('Additional placement matches')
            ->assertDontSee('Updated current rank');

        $rankedWinsOrder = $this->makePricedOrder($customer, $booster, 'Ranked Wins');
        $this->actingAs($customer)
            ->get(route('user-chats.show', ['order' => $rankedWinsOrder]))
            ->assertOk()
            ->assertSee('Extend Ranked Wins')
            ->assertSee('Additional wins')
            ->assertDontSee('New target rank')
            ->assertDontSee('Additional placement matches')
            ->assertDontSee('Updated current rank');

        $placementOrder = $this->makePricedOrder($customer, $booster, 'Placement Matches');
        $this->actingAs($customer)
            ->get(route('user-chats.show', ['order' => $placementOrder]))
            ->assertOk()
            ->assertSee('Extend Placement Matches')
            ->assertSee('Additional placement matches')
            ->assertDontSee('Additional wins')
            ->assertDontSee('New target rank')
            ->assertDontSee('Updated current rank');

        $radiantOrder = $this->makePricedOrder($customer, $booster, 'Radiant Boost');
        $this->actingAs($customer)
            ->get(route('user-chats.show', ['order' => $radiantOrder]))
            ->assertOk()
            ->assertSee('Extend Radiant Boost')
            ->assertSee('Updated current rank')
            ->assertDontSee('Additional wins')
            ->assertDontSee('Additional placement matches')
            ->assertDontSee('New target rank');
    }

    public function test_successful_extension_payment_updates_the_original_order_and_payout(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $admin = $this->makeUser('admin');
        $order = $this->makePricedOrder($customer, $booster, 'Rank Boosting');
        $originalPriceCents = $order->price_cents;
        $originalPayoutCents = $order->booster_payout_cents;

        $startCheckout = $this->actingAs($customer)
            ->post(route('customer-orders.extend.checkout', ['order' => $order]), [
                'paymentMethod' => 'fake-paid',
                'target_division' => 'Platinum I',
            ]);

        $startCheckout->assertRedirect('https://example.test/pay/fake-paid');

        $pendingCheckout = $this->latestPendingCheckout();
        $success = $this->actingAs($customer)
            ->get(route('orders.success', ['checkout' => $pendingCheckout->token]));

        $order->refresh();

        $success->assertRedirect(route('user-chats.show', ['order' => $order]));
        $success->assertSessionHas('status', 'Boost extension purchased successfully.');
        $this->assertGreaterThan($originalPriceCents, $order->price_cents);
        $this->assertGreaterThan($originalPayoutCents, $order->booster_payout_cents);
        $this->assertSame('Platinum I', data_get($order->details, 'order.desiredDivision'));
        $this->assertSame('Platinum I', data_get($order->details, 'to'));
        $this->assertDatabaseHas('order_extensions', [
            'order_id' => $order->id,
            'checkout_reference' => $pendingCheckout->reference,
            'previous_total_cents' => $originalPriceCents,
            'new_total_cents' => $order->price_cents,
            'previous_booster_payout_cents' => $originalPayoutCents,
            'new_booster_payout_cents' => $order->booster_payout_cents,
        ]);
        $this->assertDatabaseHas('order_chat_messages', [
            'sender_role' => 'system',
            'sender_name' => 'System',
            'body' => 'Boost has been successfully extended, Your booster has been notified. Just to be safe, please inform your booster in chat about the extension',
        ]);
        $this->assertDatabaseHas('order_chat_messages', [
            'sender_role' => 'system',
            'sender_name' => 'System',
            'body' => 'Boost order has been extended, please re-read the order details before continuing',
        ]);
        $this->assertDatabaseHas('order_chat_messages', [
            'sender_role' => 'system',
            'sender_name' => 'System',
            'body' => 'Boost has been extended',
        ]);
        $this->assertSame(
            OrderChatThreadType::CUSTOMER_ADMIN,
            OrderChatMessage::query()->where('body', 'Boost has been successfully extended, Your booster has been notified. Just to be safe, please inform your booster in chat about the extension')->firstOrFail()->thread->thread_type
        );
        $this->assertSame(
            OrderChatThreadType::BOOSTER_ADMIN,
            OrderChatMessage::query()->where('body', 'Boost order has been extended, please re-read the order details before continuing')->firstOrFail()->thread->thread_type
        );
        $this->assertSame(
            OrderChatThreadType::CUSTOMER_BOOSTER,
            OrderChatMessage::query()->where('body', 'Boost has been extended')->firstOrFail()->thread->thread_type
        );

        $walletSummary = app(BoosterWalletService::class)->summaryForBooster($booster);
        $this->assertSame($order->booster_payout_cents, $walletSummary['pending_earnings_cents']);

        $this->actingAs($booster)
            ->get(route('booster-chats.show', ['order' => $order]))
            ->assertOk()
            ->assertSee('Boost order has been extended, please re-read the order details before continuing')
            ->assertSee('Payout');

        $this->actingAs($customer)
            ->get(route('user-chats.show', ['order' => $order]))
            ->assertOk()
            ->assertSee('Platinum I')
            ->assertSee(number_format($order->price_cents / 100, 2));

        $this->actingAs($admin)
            ->get(route('admin-chats.show', ['order' => $order]))
            ->assertOk()
            ->assertSee('Platinum I');

        $repeat = $this->actingAs($customer)
            ->get(route('orders.success', ['checkout' => $pendingCheckout->token]));

        $repeat->assertRedirect(route('user-chats.show', ['order' => $order]));
        $this->assertSame(1, OrderExtension::query()->where('checkout_reference', $pendingCheckout->reference)->count());
        $this->assertSame(3, OrderChatMessage::query()->where('sender_role', 'system')->count());
    }

    public function test_extension_preserves_existing_promo_discount_while_payout_basis_uses_original_total(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makePricedOrder($customer, $booster, 'Rank Boosting');

        $startingOriginalPriceCents = $order->resolvedOriginalPriceCents();
        $order->forceFill([
            'price_cents' => $startingOriginalPriceCents - 500,
            'original_price_cents' => $startingOriginalPriceCents,
            'discount_amount' => 5.00,
            'booster_payout_basis_cents' => $startingOriginalPriceCents,
            'booster_payout_cents' => (int) round($startingOriginalPriceCents * Order::configuredBoosterPayoutRate()),
            'metadata' => array_merge((array) $order->metadata, [
                'pricing' => [
                    'subtotal' => round($startingOriginalPriceCents / 100, 2),
                    'originalTotal' => round($startingOriginalPriceCents / 100, 2),
                    'discountAmount' => 5.00,
                    'finalTotal' => round(($startingOriginalPriceCents - 500) / 100, 2),
                    'boosterPayoutBasis' => round($startingOriginalPriceCents / 100, 2),
                ],
            ]),
        ])->save();

        $startingChargedTotalCents = $order->customerPriceCents();

        $this->actingAs($customer)
            ->post(route('customer-orders.extend.checkout', ['order' => $order]), [
                'paymentMethod' => 'fake-paid',
                'target_division' => 'Platinum I',
            ])
            ->assertRedirect('https://example.test/pay/fake-paid');

        $pendingCheckout = $this->latestPendingCheckout();

        $this->actingAs($customer)
            ->get(route('orders.success', ['checkout' => $pendingCheckout->token]))
            ->assertRedirect(route('user-chats.show', ['order' => $order]));

        $order->refresh();
        $extension = OrderExtension::query()->latest('id')->firstOrFail();
        $walletSummary = app(BoosterWalletService::class)->summaryForBooster($booster);

        $this->assertSame(500, $order->resolvedDiscountAmountCents());
        $this->assertGreaterThan($startingOriginalPriceCents, $order->resolvedOriginalPriceCents());
        $this->assertGreaterThan($startingChargedTotalCents, $order->customerPriceCents());
        $this->assertGreaterThan($order->customerPriceCents(), $order->resolvedBoosterPayoutBasisCents());
        $this->assertSame($order->resolvedOriginalPriceCents(), $order->resolvedBoosterPayoutBasisCents());
        $this->assertSame(
            (int) round($order->resolvedOriginalPriceCents() * Order::configuredBoosterPayoutRate()),
            $order->resolvedBoosterPayoutCents()
        );
        $this->assertSame($order->resolvedBoosterPayoutCents(), $walletSummary['pending_earnings_cents']);
        $this->assertSame($startingChargedTotalCents, $extension->previous_total_cents);
        $this->assertSame($order->customerPriceCents(), $extension->new_total_cents);
        $this->assertSame($startingOriginalPriceCents, data_get($extension->metadata, 'previousOriginalTotalCents'));
        $this->assertSame($order->resolvedOriginalPriceCents(), data_get($extension->metadata, 'newOriginalTotalCents'));
        $this->assertSame($order->resolvedBoosterPayoutBasisCents(), data_get($extension->metadata, 'boosterPayoutBasisCents'));
    }

    public function test_pause_and_continue_update_persistent_order_status_and_booster_notice(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makePricedOrder($customer, $booster, 'Rank Boosting');

        $pause = $this->actingAs($customer)
            ->post(route('customer-orders.pause', ['order' => $order]));

        $pause->assertRedirect(route('user-chats.show', ['order' => $order]));
        $pause->assertSessionHas('status', 'Boost paused.');
        $order->refresh();
        $this->assertSame(OrderStatus::PAUSED, $order->status);

        $this->actingAs($customer)
            ->get(route('user-chats.show', ['order' => $order]))
            ->assertOk()
            ->assertSee('Continue Boost')
            ->assertSee(route('customer-orders.resume', ['order' => $order]), false);

        $this->actingAs($booster)
            ->get(route('booster-chats.show', ['order' => $order]))
            ->assertOk()
            ->assertSee('Boost Paused, Contact Admin');

        $resume = $this->actingAs($customer)
            ->post(route('customer-orders.resume', ['order' => $order]));

        $resume->assertRedirect(route('user-chats.show', ['order' => $order]));
        $resume->assertSessionHas('status', 'Boost resumed.');
        $order->refresh();
        $this->assertSame(OrderStatus::IN_PROGRESS, $order->status);

        $this->actingAs($customer)
            ->get(route('user-chats.show', ['order' => $order]))
            ->assertOk()
            ->assertSee('Pause Boost')
            ->assertSee(route('customer-orders.pause', ['order' => $order]), false);

        $this->actingAs($booster)
            ->get(route('booster-chats.show', ['order' => $order]))
            ->assertOk()
            ->assertDontSee('Boost Paused, Contact Admin');
    }

    public function test_customer_cannot_manage_someone_elses_rank_tracker_actions(): void
    {
        $owner = $this->makeUser('customer');
        $otherCustomer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makePricedOrder($owner, $booster, 'Rank Boosting');

        $this->actingAs($otherCustomer)
            ->post(route('customer-orders.extend.checkout', ['order' => $order]), [
                'paymentMethod' => 'fake-paid',
                'target_division' => 'Platinum I',
            ])
            ->assertForbidden();

        $this->actingAs($otherCustomer)
            ->post(route('customer-orders.pause', ['order' => $order]))
            ->assertForbidden();

        $this->actingAs($otherCustomer)
            ->post(route('customer-orders.tips.booster.checkout', ['order' => $order]), [
                'paymentMethod' => 'fake-paid',
                'amount' => '12.00',
            ])
            ->assertForbidden();
    }

    public function test_tip_booster_payment_adds_funds_to_the_correct_booster_wallet(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makePricedOrder($customer, $booster, 'Rank Boosting');

        $startCheckout = $this->actingAs($customer)
            ->post(route('customer-orders.tips.booster.checkout', ['order' => $order]), [
                'paymentMethod' => 'fake-paid',
                'amount' => '15.00',
            ]);

        $startCheckout->assertRedirect('https://example.test/pay/fake-paid');

        $pendingCheckout = $this->latestPendingCheckout();
        $success = $this->actingAs($customer)
            ->get(route('orders.success', ['checkout' => $pendingCheckout->token]));

        $success->assertRedirect(route('user-chats.show', ['order' => $order]));
        $success->assertSessionHas('status', 'Tip sent to the booster successfully.');
        $this->assertDatabaseHas('order_tips', [
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'booster_id' => $booster->id,
            'recipient_type' => OrderTip::RECIPIENT_BOOSTER,
            'amount_cents' => 1500,
        ]);

        $walletSummary = app(BoosterWalletService::class)->summaryForBooster($booster);
        $this->assertSame(1500, $walletSummary['total_tip_cents']);
        $this->assertSame($order->booster_payout_cents, $walletSummary['pending_earnings_cents']);
        $this->assertSame(1500, $walletSummary['current_balance_cents']);
    }

    public function test_tip_admin_is_stored_and_reported_separately_in_admin_finances(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $admin = $this->makeUser('admin');
        $order = $this->makePricedOrder($customer, $booster, 'Rank Boosting');

        $startCheckout = $this->actingAs($customer)
            ->post(route('customer-orders.tips.admin.checkout', ['order' => $order]), [
                'paymentMethod' => 'fake-paid',
                'amount' => '25.00',
            ]);

        $startCheckout->assertRedirect('https://example.test/pay/fake-paid');

        $pendingCheckout = $this->latestPendingCheckout();
        $success = $this->actingAs($customer)
            ->get(route('orders.success', ['checkout' => $pendingCheckout->token]));

        $success->assertRedirect(route('user-chats.show', ['order' => $order]));
        $success->assertSessionHas('status', 'Tip sent to admin successfully.');
        $this->assertDatabaseHas('order_tips', [
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'booster_id' => null,
            'recipient_type' => OrderTip::RECIPIENT_ADMIN,
            'amount_cents' => 2500,
        ]);

        $dashboardPayload = app(AdminDashboardQuery::class)->execute('all_time');
        $this->assertSame($order->price_cents, $dashboardPayload['totalSaleCents']);
        $this->assertSame($order->booster_payout_cents, $dashboardPayload['estimatedBoosterPayoutsCents']);
        $this->assertSame(2500, $dashboardPayload['adminTipsCents']);

        $this->actingAs($admin)
            ->get(route('admin-dashboard'))
            ->assertOk()
            ->assertSee('Tips')
            ->assertSee('25.00')
            ->assertSee('Admin tips tracked separately from order revenue.');
    }

    public function test_dashboard_and_booster_wallet_fall_back_to_zero_tips_when_tip_table_is_missing(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makePricedOrder($customer, $booster, 'Rank Boosting');

        Schema::drop('order_tips');

        $dashboardPayload = app(AdminDashboardQuery::class)->execute('all_time');
        $walletSummary = app(BoosterWalletService::class)->summaryForBooster($booster->fresh());

        $this->assertSame($order->price_cents, $dashboardPayload['totalSaleCents']);
        $this->assertSame($order->booster_payout_cents, $dashboardPayload['estimatedBoosterPayoutsCents']);
        $this->assertSame(0, $dashboardPayload['adminTipsCents']);
        $this->assertSame(0, $walletSummary['total_tip_cents']);
        $this->assertSame($order->booster_payout_cents, $walletSummary['pending_earnings_cents']);
    }

    public function test_legacy_extension_payloads_still_produce_a_working_extension_checkout(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');

        $order = Order::query()->create([
            'user_id' => $customer->id,
            'booster_id' => $booster->id,
            'order_number' => (string) Str::orderedUuid(),
            'product' => 'Rank Boosting',
            'status' => OrderStatus::IN_PROGRESS,
            'payment_status' => 'paid',
            'price_cents' => 4129,
            'original_price_cents' => 4129,
            'booster_payout_rate' => Order::configuredBoosterPayoutPercentage(),
            'booster_payout_cents' => (int) round(4129 * Order::configuredBoosterPayoutRate()),
            'booster_payout_basis_cents' => 4129,
            'currency' => 'USD',
            'details' => [
                'service' => 'Rank Boosting',
                'from' => 'Silver 1',
                'to' => 'Gold 1',
                'rr' => 55,
                'average_rr' => '16 OR LOWER',
                'boostMode' => 'Account Shared',
                'region' => 'NA',
                'platform' => 'PC',
                'order' => [
                    'orderType' => 'Rank Boosting',
                    'currentDivision' => 'Silver 1',
                    'desiredDivision' => 'Gold 1',
                    'region' => 'NA',
                    'platform' => 'PC',
                ],
            ],
            'metadata' => [
                'customer' => [
                    'email' => $customer->email,
                ],
                'contactMethod' => 'discord',
                'paymentMethod' => 'fake-paid',
                'paymentProvider' => 'fake-paid',
            ],
            'contact_method' => 'discord',
            'discord' => 'customer#1234',
            'assigned_at' => now(),
            'paid_at' => now(),
        ]);

        $this->actingAs($customer)
            ->post(route('customer-orders.extend.checkout', ['order' => $order]), [
                'paymentMethod' => 'fake-paid',
                'target_division' => 'Platinum I',
            ])
            ->assertRedirect('https://example.test/pay/fake-paid');
    }

    protected function latestPendingCheckout(): PendingCheckout
    {
        $record = PendingCheckoutRecord::query()->latest('id')->firstOrFail();

        return app(PendingCheckoutStore::class)->find($record->token);
    }

    protected function makeUser(string $role, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'account_status' => 'active',
        ], $overrides));
    }

    protected function makePricedOrder(User $customer, ?User $booster, string $serviceType): Order
    {
        $pricingInput = $this->pricingInputFor($serviceType);
        $pricedPayload = app(ValorantPricingEngine::class)->calculateOrFail(
            $pricingInput,
            ['allowExtendedRankedWins' => ($pricingInput['serviceType'] ?? null) === 'Ranked Wins']
        );
        $priceCents = (int) round(((float) data_get($pricedPayload, 'pricing.total', 0)) * 100);

        return Order::query()->create([
            'user_id' => $customer->id,
            'booster_id' => $booster?->id,
            'order_number' => (string) Str::orderedUuid(),
            'product' => $serviceType,
            'status' => OrderStatus::IN_PROGRESS,
            'payment_status' => 'paid',
            'price_cents' => $priceCents,
            'original_price_cents' => $priceCents,
            'booster_payout_rate' => Order::configuredBoosterPayoutPercentage(),
            'booster_payout_cents' => (int) round($priceCents * Order::configuredBoosterPayoutRate()),
            'booster_payout_basis_cents' => $priceCents,
            'currency' => 'USD',
            'details' => [
                'game' => 'VALORANT',
                'service' => $serviceType,
                'from' => data_get($pricedPayload, 'currentDivision', 'Unranked'),
                'to' => data_get($pricedPayload, 'desiredDivision', 'Unranked'),
                'currentRR' => data_get($pricedPayload, 'currentRR'),
                'averageRR' => data_get($pricedPayload, 'averageRR'),
                'region' => data_get($pricedPayload, 'region', 'EU'),
                'platform' => data_get($pricedPayload, 'platform', 'PC'),
                'accountType' => data_get($pricedPayload, 'accountType', 'Account Shared'),
                'addons' => data_get($pricedPayload, 'addons', []),
                'order' => $pricedPayload,
            ],
            'metadata' => [
                'customer' => [
                    'email' => $customer->email,
                ],
                'contactMethod' => 'discord',
                'paymentMethod' => 'fake-paid',
                'paymentProvider' => 'fake-paid',
                'pricing' => [
                    'subtotal' => round($priceCents / 100, 2),
                    'originalTotal' => round($priceCents / 100, 2),
                    'discountAmount' => 0,
                    'finalTotal' => round($priceCents / 100, 2),
                    'boosterPayoutBasis' => round($priceCents / 100, 2),
                ],
            ],
            'contact_method' => 'discord',
            'discord' => 'customer#1234',
            'assigned_at' => $booster ? now() : null,
            'paid_at' => now(),
        ]);
    }

    protected function pricingInputFor(string $serviceType): array
    {
        return match ($serviceType) {
            'Ranked Wins' => [
                'serviceType' => 'Ranked Wins',
                'currentDivision' => 'Gold I',
                'region' => 'EU',
                'platform' => 'PC',
                'boostMode' => 'Account Shared',
                'numberOfWins' => 10,
                'selectedAddons' => ['Offline Mode'],
            ],
            'Placement Matches' => [
                'serviceType' => 'Placement Matches',
                'currentDivision' => 'Gold I',
                'region' => 'NA',
                'platform' => 'PC',
                'boostMode' => 'Account Shared',
                'numberOfPlacementGames' => 3,
                'selectedAddons' => ['Offline Mode'],
            ],
            'Radiant Boost' => [
                'serviceType' => 'Radiant Boost',
                'currentDivision' => 'Immortal I',
                'desiredDivision' => 'Radiant',
                'avgRRPerWin' => '18 OR LOWER',
                'region' => 'EU',
                'platform' => 'PC',
                'boostMode' => 'Account Shared',
                'selectedAddons' => ['Offline Mode'],
            ],
            default => [
                'serviceType' => 'Rank Boosting',
                'currentDivision' => 'Silver I',
                'desiredDivision' => 'Gold I',
                'currentRR' => 50,
                'avgRRPerWin' => '18 OR LOWER',
                'region' => 'EU',
                'platform' => 'PC',
                'boostMode' => 'Account Shared',
                'selectedAddons' => ['Offline Mode'],
            ],
        };
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
                            description: 'Instant paid test provider.',
                            notice: 'Paid instantly in tests.',
                            submitLabel: 'Continue to Payment',
                            isAvailable: true,
                            isDefault: true,
                        );
                    }

                    public function initialize(PendingCheckout $pendingCheckout, PaymentCheckoutData $checkoutData): PaymentInitializationResult
                    {
                        return PaymentInitializationResult::redirect('https://example.test/pay/fake-paid');
                    }

                    public function verify(PendingCheckout $pendingCheckout, array $payload = []): PaymentVerificationResult
                    {
                        $suffix = Str::lower(str_replace('-', '', $pendingCheckout->reference));

                        return new PaymentVerificationResult(true, [
                            'payment_status' => 'paid',
                            'payment_reference' => 'pi_'.$suffix,
                            'stripe_session_id' => 'cs_'.$suffix,
                        ]);
                    }
                },
            ]);
        });
    }
}
