<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminManualOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_manual_order_for_any_customer_and_booster(): void
    {
        Http::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);
        $booster = User::factory()->create([
            'role' => 'booster',
            'account_status' => 'suspended',
        ]);

        $response = $this->actingAs($admin)->post(route('admin-orders.store-manual'), [
            'user_id' => $customer->id,
            'booster_id' => $booster->id,
            'product' => 'Rank Boosting',
            'game' => 'Valorant',
            'status' => 'InProgress',
            'payment_status' => 'paid',
            'price' => '249.99',
            'currency' => 'usd',
            'contact_method' => 'discord',
            'discord' => 'demo#1234',
            'current_division' => 'Gold II',
            'desired_division' => 'Diamond I',
            'average_rr' => '18 OR LOWER',
            'region' => 'NA',
            'platform' => 'PC',
            'account_type' => 'Account Shared',
            'addons' => 'Express, Solo queue',
            'notes' => 'VIP manual order',
        ]);

        $response->assertRedirect(route('admin-custom-order'));

        $order = Order::query()->firstOrFail();

        $this->assertSame($customer->id, $order->user_id);
        $this->assertSame($booster->id, $order->booster_id);
        $this->assertSame('Rank Boosting', $order->product);
        $this->assertSame('InProgress', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(24999, $order->price_cents);
        $this->assertSame('USD', $order->currency);
        $this->assertTrue($order->is_custom);
        $this->assertNotNull($order->paid_at);
        $this->assertNotNull($order->assigned_at);
        $this->assertSame('Rank Boosting', data_get($order->details, 'order.orderType'));
        $this->assertSame('Gold II', data_get($order->details, 'order.currentDivision'));
        $this->assertSame('Diamond I', data_get($order->details, 'order.desiredDivision'));
        $this->assertSame(['Express Order', 'Solo-Queue Only'], data_get($order->details, 'order.addons'));
        $this->assertSame($customer->email, data_get($order->metadata, 'customer.email'));
    }

    public function test_admin_can_reassign_customer_and_preserve_nested_details_when_updating(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $oldCustomer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);
        $newCustomer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);
        $booster = User::factory()->create([
            'role' => 'booster',
            'account_status' => 'active',
        ]);

        $order = Order::query()->create([
            'user_id' => $oldCustomer->id,
            'booster_id' => $booster->id,
            'order_number' => (string) Str::orderedUuid(),
            'product' => 'Placement Matches',
            'status' => 'Pending',
            'payment_status' => 'pending',
            'price_cents' => 12000,
            'booster_payout_rate' => 60,
            'booster_payout_cents' => 7200,
            'currency' => 'USD',
            'details' => [
                'order' => [
                    'orderType' => 'Placement Matches',
                    'currentDivision' => 'Silver I',
                ],
            ],
            'metadata' => [
                'customer' => [
                    'firstName' => $oldCustomer->first_name,
                    'lastName' => $oldCustomer->last_name,
                    'email' => $oldCustomer->email,
                ],
            ],
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($admin)->patch(route('admin-orders.update', $order), [
            'status' => 'Completed',
            'payment_status' => 'paid',
            'user_id' => $newCustomer->id,
            'booster_id' => '',
            'product' => 'Radiant Boost',
            'price' => '315.50',
            'currency' => 'usd',
            'details' => [
                'order.currentDivision' => 'Ascendant II',
                'order.desiredDivision' => 'Radiant',
                'notes' => 'Updated by admin',
            ],
        ]);

        $response->assertRedirect(route('admin-orders.edit', $order));

        $order->refresh();

        $this->assertSame($newCustomer->id, $order->user_id);
        $this->assertNull($order->booster_id);
        $this->assertNull($order->assigned_at);
        $this->assertSame('Radiant Boost', $order->product);
        $this->assertSame('Completed', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(31550, $order->price_cents);
        $this->assertSame('USD', $order->currency);
        $this->assertSame('Radiant Boost', data_get($order->details, 'service'));
        $this->assertSame('Radiant Boost', data_get($order->details, 'order.orderType'));
        $this->assertSame('Ascendant II', data_get($order->details, 'order.currentDivision'));
        $this->assertSame('Radiant', data_get($order->details, 'order.desiredDivision'));
        $this->assertSame('Updated by admin', data_get($order->details, 'notes'));
        $this->assertSame($newCustomer->email, data_get($order->metadata, 'customer.email'));
        $this->assertNotNull($order->paid_at);
    }

    public function test_admin_order_edit_page_uses_service_dropdown_with_selected_product(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $order = Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => (string) Str::orderedUuid(),
            'product' => 'Rank Boosting',
            'status' => 'Pending',
            'payment_status' => 'pending',
            'price_cents' => 15000,
            'booster_payout_rate' => 60,
            'booster_payout_cents' => 9000,
            'currency' => 'USD',
            'details' => [
                'service' => 'Rank Boosting',
                'order' => [
                    'orderType' => 'Rank Boosting',
                ],
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('admin-orders.edit', $order))
            ->assertOk()
            ->assertSee('<select class="form-select" name="product" required>', false)
            ->assertSee('>Rank Boosting</option>', false)
            ->assertDontSee('<input type="text" class="form-control" name="product"', false);
    }

    public function test_admin_manual_order_without_booster_defaults_to_pending_even_if_a_status_is_posted(): void
    {
        Http::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $response = $this->actingAs($admin)->post(route('admin-orders.store-manual'), [
            'user_id' => $customer->id,
            'product' => 'Rank Boosting',
            'game' => 'Valorant',
            'status' => 'Paused',
            'payment_status' => 'paid',
            'price' => '149.99',
            'currency' => 'usd',
        ]);

        $response->assertRedirect(route('admin-custom-order'));

        $order = Order::query()->firstOrFail();

        $this->assertSame('Pending', $order->status);
        $this->assertSame('paid', $order->payment_status);
    }

    public function test_admin_manual_orders_store_normalized_specific_agents_when_the_addon_is_selected(): void
    {
        Http::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $agentUuids = $this->agentUuids(3);

        $response = $this->actingAs($admin)->post(route('admin-orders.store-manual'), [
            'user_id' => $customer->id,
            'product' => 'Rank Boosting',
            'game' => 'Valorant',
            'status' => 'Pending',
            'payment_status' => 'paid',
            'price' => '149.99',
            'currency' => 'usd',
            'addons' => ['Specific Agents', 'Offline Mode'],
            'specific_agents' => $agentUuids,
        ]);

        $response->assertRedirect(route('admin-custom-order'));

        $order = Order::query()->firstOrFail();

        $this->assertSame($agentUuids, data_get($order->details, 'specificAgents'));
        $this->assertSame($agentUuids, data_get($order->details, 'order.specificAgents'));
    }

    public function test_admin_manual_orders_require_specific_agents_when_the_addon_is_selected(): void
    {
        Http::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin-custom-order'))
            ->post(route('admin-orders.store-manual'), [
                'user_id' => $customer->id,
                'product' => 'Rank Boosting',
                'game' => 'Valorant',
                'status' => 'Pending',
                'payment_status' => 'paid',
                'price' => '149.99',
                'currency' => 'usd',
                'addons' => ['Specific Agents'],
                'specific_agents' => [],
            ]);

        $response->assertRedirect(route('admin-custom-order'));
        $response->assertSessionHasErrors('specific_agents');
    }

    public function test_admin_manual_orders_reject_specific_agents_below_the_minimum_selection(): void
    {
        Http::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin-custom-order'))
            ->post(route('admin-orders.store-manual'), [
                'user_id' => $customer->id,
                'product' => 'Rank Boosting',
                'game' => 'Valorant',
                'status' => 'Pending',
                'payment_status' => 'paid',
                'price' => '149.99',
                'currency' => 'usd',
                'addons' => ['Specific Agents'],
                'specific_agents' => $this->agentUuids(2),
            ]);

        $response->assertRedirect(route('admin-custom-order'));
        $response->assertSessionHasErrors('specific_agents');
    }

    public function test_admin_manual_orders_reject_duplicate_specific_agents_even_if_three_unique_agents_remain(): void
    {
        Http::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        [$firstAgent, $secondAgent, $thirdAgent] = $this->agentUuids(3);

        $response = $this->actingAs($admin)
            ->from(route('admin-custom-order'))
            ->post(route('admin-orders.store-manual'), [
                'user_id' => $customer->id,
                'product' => 'Rank Boosting',
                'game' => 'Valorant',
                'status' => 'Pending',
                'payment_status' => 'paid',
                'price' => '149.99',
                'currency' => 'usd',
                'addons' => ['Specific Agents'],
                'specific_agents' => [$firstAgent, $firstAgent, $secondAgent, $thirdAgent],
            ]);

        $response->assertRedirect(route('admin-custom-order'));
        $response->assertSessionHasErrors('specific_agents');
        $this->assertSame(0, Order::count());
    }

    public function test_admin_manual_orders_reject_unsupported_specific_agents(): void
    {
        Http::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin-custom-order'))
            ->post(route('admin-orders.store-manual'), [
                'user_id' => $customer->id,
                'product' => 'Rank Boosting',
                'game' => 'Valorant',
                'status' => 'Pending',
                'payment_status' => 'paid',
                'price' => '149.99',
                'currency' => 'usd',
                'addons' => ['Specific Agents'],
                'specific_agents' => [...$this->agentUuids(3), 'not-a-real-agent'],
            ]);

        $response->assertRedirect(route('admin-custom-order'));
        $response->assertSessionHasErrors('specific_agents');
        $this->assertSame(0, Order::count());
    }

    public function test_admin_manual_orders_store_one_trick_agent_when_the_addon_is_selected(): void
    {
        Http::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $agentUuid = $this->agentUuids(1);

        $response = $this->actingAs($admin)->post(route('admin-orders.store-manual'), [
            'user_id' => $customer->id,
            'product' => 'Rank Boosting',
            'game' => 'Valorant',
            'status' => 'Pending',
            'payment_status' => 'paid',
            'price' => '149.99',
            'currency' => 'usd',
            'addons' => ['One-Trick Agent', 'Offline Mode'],
            'one_trick_agent' => $agentUuid,
        ]);

        $response->assertRedirect(route('admin-custom-order'));

        $order = Order::query()->firstOrFail();

        $this->assertSame($agentUuid, data_get($order->details, 'oneTrickAgent'));
        $this->assertSame($agentUuid, data_get($order->details, 'order.oneTrickAgent'));
    }

    public function test_admin_manual_orders_require_one_trick_agent_when_the_addon_is_selected(): void
    {
        Http::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin-custom-order'))
            ->post(route('admin-orders.store-manual'), [
                'user_id' => $customer->id,
                'product' => 'Rank Boosting',
                'game' => 'Valorant',
                'status' => 'Pending',
                'payment_status' => 'paid',
                'price' => '149.99',
                'currency' => 'usd',
                'addons' => ['One-Trick Agent'],
                'one_trick_agent' => [],
            ]);

        $response->assertRedirect(route('admin-custom-order'));
        $response->assertSessionHasErrors('one_trick_agent');
    }

    public function test_admin_manual_orders_reject_multiple_one_trick_agents(): void
    {
        Http::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin-custom-order'))
            ->post(route('admin-orders.store-manual'), [
                'user_id' => $customer->id,
                'product' => 'Rank Boosting',
                'game' => 'Valorant',
                'status' => 'Pending',
                'payment_status' => 'paid',
                'price' => '149.99',
                'currency' => 'usd',
                'addons' => ['One-Trick Agent'],
                'one_trick_agent' => $this->agentUuids(2),
            ]);

        $response->assertRedirect(route('admin-custom-order'));
        $response->assertSessionHasErrors('one_trick_agent');
    }

    public function test_admin_manual_orders_allow_admin_override_for_self_play_and_customer_restricted_addons(): void
    {
        Http::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin-custom-order'))
            ->post(route('admin-orders.store-manual'), [
                'user_id' => $customer->id,
                'product' => 'Rank Boosting',
                'game' => 'Valorant',
                'status' => 'Pending',
                'payment_status' => 'paid',
                'price' => '149.99',
                'currency' => 'usd',
                'current_division' => 'Ascendant III',
                'desired_division' => 'Immortal II',
                'account_type' => 'Self-Play',
                'addons' => ['Offline Mode', 'Express Order'],
            ]);

        $response->assertRedirect(route('admin-custom-order'));
        $response->assertSessionHasNoErrors();

        $order = Order::query()->firstOrFail();

        $this->assertSame(['Offline Mode', 'Express Order'], data_get($order->details, 'order.addons'));
        $this->assertTrue((bool) data_get($order->metadata, 'adminOverride.customerRestrictionsBypassed'));
        $this->assertSame('manual-price', data_get($order->metadata, 'adminOverride.pricingMode'));
    }

    public function test_admin_manual_orders_allow_specific_agents_with_one_trick_agent_together_when_admin_overrides(): void
    {
        Http::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);
        $specificAgents = $this->agentUuids(3);
        $oneTrickAgent = $this->agentUuids(1);

        $response = $this->actingAs($admin)
            ->from(route('admin-custom-order'))
            ->post(route('admin-orders.store-manual'), [
                'user_id' => $customer->id,
                'product' => 'Rank Boosting',
                'game' => 'Valorant',
                'status' => 'Pending',
                'payment_status' => 'paid',
                'price' => '149.99',
                'currency' => 'usd',
                'account_type' => 'Account Shared',
                'addons' => ['Specific Agents', 'One-Trick Agent'],
                'specific_agents' => $specificAgents,
                'one_trick_agent' => $oneTrickAgent,
            ]);

        $response->assertRedirect(route('admin-custom-order'));
        $response->assertSessionHasNoErrors();

        $order = Order::query()->firstOrFail();

        $this->assertSame(['Specific Agents', 'One-Trick Agent'], data_get($order->details, 'order.addons'));
        $this->assertSame($specificAgents, data_get($order->details, 'order.specificAgents'));
        $this->assertSame($oneTrickAgent, data_get($order->details, 'order.oneTrickAgent'));
    }

    public function test_admin_manual_orders_require_a_custom_price_when_customer_flow_preview_cannot_price_the_override(): void
    {
        Http::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin-custom-order'))
            ->post(route('admin-orders.store-manual'), [
                'user_id' => $customer->id,
                'product' => 'Rank Boosting',
                'game' => 'Valorant',
                'status' => 'Pending',
                'payment_status' => 'paid',
                'currency' => 'usd',
                'current_division' => 'Ascendant III',
                'desired_division' => 'Immortal II',
                'account_type' => 'Self-Play',
                'addons' => ['Offline Mode', 'Express Order'],
            ]);

        $response->assertRedirect(route('admin-custom-order'));
        $response->assertSessionHasErrors('price');
        $this->assertSame(0, Order::count());
    }

    public function test_admin_order_updates_reject_conflicting_addons_in_details(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $order = Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => (string) Str::orderedUuid(),
            'product' => 'Rank Boosting',
            'status' => 'Pending',
            'payment_status' => 'pending',
            'price_cents' => 15000,
            'booster_payout_rate' => 60,
            'booster_payout_cents' => 9000,
            'currency' => 'USD',
            'details' => [
                'service' => 'Rank Boosting',
                'accountType' => 'Account Shared',
                'addons' => ['Express Order'],
                'order' => [
                    'orderType' => 'Rank Boosting',
                    'accountType' => 'Account Shared',
                    'addons' => ['Express Order'],
                ],
            ],
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin-orders.edit', $order))
            ->patch(route('admin-orders.update', $order), [
                'status' => 'Pending',
                'payment_status' => 'pending',
                'details' => [
                    'accountType' => 'Account Shared',
                    'addons' => ['Specific Agents', 'One-Trick Agent'],
                    'specificAgents' => $this->agentUuids(3),
                    'oneTrickAgent' => $this->agentUuids(1),
                ],
            ]);

        $response->assertRedirect(route('admin-orders.edit', $order));
        $response->assertSessionHasErrors('details');
    }

    public function test_admin_order_updates_reject_duplicate_one_trick_agent_payloads(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        [$agentUuid] = $this->agentUuids(1);

        $order = Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => (string) Str::orderedUuid(),
            'product' => 'Rank Boosting',
            'status' => 'Pending',
            'payment_status' => 'pending',
            'price_cents' => 15000,
            'booster_payout_rate' => 60,
            'booster_payout_cents' => 9000,
            'currency' => 'USD',
            'details' => [
                'service' => 'Rank Boosting',
                'accountType' => 'Account Shared',
                'addons' => ['Express Order'],
                'order' => [
                    'orderType' => 'Rank Boosting',
                    'accountType' => 'Account Shared',
                    'addons' => ['Express Order'],
                ],
            ],
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin-orders.edit', $order))
            ->patch(route('admin-orders.update', $order), [
                'status' => 'Pending',
                'payment_status' => 'pending',
                'details' => [
                    'accountType' => 'Account Shared',
                    'addons' => ['One-Trick Agent'],
                    'oneTrickAgent' => [$agentUuid, $agentUuid],
                ],
            ]);

        $response->assertRedirect(route('admin-orders.edit', $order));
        $response->assertSessionHasErrors('details');
    }

    public function test_admin_custom_order_updates_preserve_override_only_addons_and_manual_price(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);
        $specificAgents = $this->agentUuids(3);
        $oneTrickAgent = $this->agentUuids(1);

        $order = Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => (string) Str::orderedUuid(),
            'product' => 'Rank Boosting',
            'status' => 'Pending',
            'payment_status' => 'pending',
            'price_cents' => 14999,
            'booster_payout_rate' => 60,
            'booster_payout_cents' => 8999,
            'currency' => 'USD',
            'is_custom' => true,
            'details' => [
                'service' => 'Rank Boosting',
                'from' => 'Ascendant III',
                'to' => 'Immortal II',
                'accountType' => 'Self-Play',
                'addons' => ['Offline Mode', 'Express Order', 'Solo-Queue Only'],
                'order' => [
                    'orderType' => 'Rank Boosting',
                    'currentDivision' => 'Ascendant III',
                    'desiredDivision' => 'Immortal II',
                    'accountType' => 'Self-Play',
                    'addons' => ['Offline Mode', 'Express Order', 'Solo-Queue Only'],
                ],
                'adminOverride' => [
                    'enabled' => true,
                    'customerRestrictionsBypassed' => true,
                ],
            ],
            'metadata' => [
                'source' => 'admin-custom-order',
                'adminOverride' => [
                    'enabled' => true,
                    'customerRestrictionsBypassed' => true,
                    'pricingMode' => 'manual-price',
                    'manualPriceApplied' => true,
                    'manualPriceCents' => 14999,
                    'manualPrice' => 149.99,
                ],
            ],
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin-orders.edit', $order))
            ->patch(route('admin-orders.update', $order), [
                'status' => 'InProgress',
                'payment_status' => 'paid',
                'price' => '199.50',
                'currency' => 'usd',
                'details' => [
                    'accountType' => 'Self-Play',
                    'addons' => ['Specific Agents', 'One-Trick Agent'],
                    'specificAgents' => $specificAgents,
                    'oneTrickAgent' => $oneTrickAgent,
                ],
            ]);

        $response->assertRedirect(route('admin-orders.edit', $order));
        $response->assertSessionHasNoErrors();

        $order->refresh();

        $this->assertSame('InProgress', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(19950, $order->price_cents);
        $this->assertSame('USD', $order->currency);
        $this->assertSame('Ascendant III', data_get($order->details, 'from'));
        $this->assertSame('Immortal II', data_get($order->details, 'to'));
        $this->assertSame(['Specific Agents', 'One-Trick Agent'], data_get($order->details, 'addons'));
        $this->assertSame(['Specific Agents', 'One-Trick Agent'], data_get($order->details, 'order.addons'));
        $this->assertSame($specificAgents, data_get($order->details, 'specificAgents'));
        $this->assertSame($specificAgents, data_get($order->details, 'order.specificAgents'));
        $this->assertSame($oneTrickAgent, data_get($order->details, 'oneTrickAgent'));
        $this->assertSame($oneTrickAgent, data_get($order->details, 'order.oneTrickAgent'));
        $this->assertTrue((bool) data_get($order->metadata, 'adminOverride.enabled'));
        $this->assertTrue((bool) data_get($order->metadata, 'adminOverride.customerRestrictionsBypassed'));
        $this->assertSame('manual-price', data_get($order->metadata, 'adminOverride.pricingMode'));
        $this->assertSame(19950, data_get($order->metadata, 'adminOverride.manualPriceCents'));
        $this->assertSame(199.5, data_get($order->metadata, 'adminOverride.manualPrice'));
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
}
