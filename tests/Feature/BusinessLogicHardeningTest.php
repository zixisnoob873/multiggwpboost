<?php

namespace Tests\Feature;

use App\Enums\OrderChatThreadType;
use App\Models\Order;
use App\Models\OrderChatMessage;
use App\Models\User;
use App\Services\Chat\EnsureOrderChatThreads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessLogicHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_delete_action_is_removed_from_admin_ui_and_routes(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
            'first_name' => 'Casey',
            'last_name' => 'Player',
            'name' => 'Casey Player',
            'email' => 'casey@example.com',
        ]);

        $this->assertFalse(Route::has('admin-customers.destroy'));

        $this->actingAs($admin)
            ->get(route('admin-customers.index'))
            ->assertOk()
            ->assertDontSee('Delete Customer')
            ->assertDontSee('Delete</button>', false);

        $this->actingAs($admin)
            ->get(route('admin-customers.edit', $customer))
            ->assertOk()
            ->assertDontSee('Delete Customer');

        $response = $this->actingAs($admin)->delete("/admin/customers/{$customer->id}");

        $this->assertContains($response->getStatusCode(), [404, 405]);
    }

    public function test_booster_delete_action_is_removed_from_admin_ui_and_routes(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $booster = User::factory()->create([
            'role' => 'booster',
            'account_status' => 'active',
            'first_name' => 'Blake',
            'last_name' => 'Carry',
            'name' => 'Blake Carry',
            'email' => 'booster@example.com',
        ]);

        $this->assertFalse(Route::has('admin-boosters.destroy'));

        $this->actingAs($admin)
            ->get(route('admin-boosters.index'))
            ->assertOk()
            ->assertDontSee('Delete Booster')
            ->assertDontSee('Delete</button>', false);

        $this->actingAs($admin)
            ->get(route('admin-boosters.edit', ['booster' => $booster->nickname]))
            ->assertOk()
            ->assertDontSee('Delete Booster');

        $response = $this->actingAs($admin)->delete("/admin/boosters/{$booster->id}");

        $this->assertContains($response->getStatusCode(), [404, 405]);
    }

    public function test_admin_can_update_order_progress_without_chat_endpoints(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
            'email' => 'customer@example.com',
        ]);
        $booster = User::factory()->create([
            'role' => 'booster',
            'account_status' => 'active',
            'email' => 'booster@example.com',
        ]);
        $order = $this->makeOrder($customer, $booster, [
            'details' => [
                'notes' => 'Customer note',
                'adminNotes' => 'Internal note',
                'order' => [
                    'orderType' => 'Rank Boosting',
                    'currentDivision' => 'Gold I',
                    'desiredDivision' => 'Platinum I',
                ],
            ],
            'metadata' => [
                'customer' => [
                    'email' => $customer->email,
                ],
                'internalFlag' => 'secret',
            ],
            'whatsapp' => '+15555555555',
            'discord' => 'customer#1234',
            'stripe_session_id' => 'cs_test_123',
            'payment_reference' => 'pi_test_123',
        ]);

        $response = $this->actingAs($admin)->patch(route('orders.progress.update', $order), [
            'current_rank' => 'Gold II',
            'current_rr' => 50,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Progress updated.');

        $order->refresh();

        $this->assertSame(50, (int) data_get($order->details, 'progress.pct'));
        $this->assertSame('Gold II', data_get($order->details, 'progress.currentRank'));
        $this->assertSame(50, data_get($order->details, 'progress.currentRR'));
        $this->assertSame($admin->name, data_get($order->details, 'progress.updatedBy'));
    }

    public function test_customer_cannot_update_order_progress(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
            'email' => 'customer@example.com',
        ]);
        $booster = User::factory()->create([
            'role' => 'booster',
            'account_status' => 'active',
            'email' => 'booster@example.com',
        ]);
        $order = $this->makeOrder($customer, $booster, [
            'details' => [
                'notes' => 'Customer note',
                'adminNotes' => 'Internal note',
                'order' => [
                    'orderType' => 'Rank Boosting',
                    'currentDivision' => 'Gold I',
                    'desiredDivision' => 'Platinum I',
                ],
            ],
            'stripe_session_id' => 'cs_test_456',
            'payment_reference' => 'pi_test_456',
        ]);

        $response = $this->actingAs($customer)->patch(route('orders.progress.update', $order), [
            'current_rank' => 'Gold II',
            'current_rr' => 15,
        ]);

        $response->assertForbidden();
    }

    public function test_legacy_my_order_route_redirects_to_supported_chat_workspace(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);
        $order = $this->makeOrder($customer);

        $response = $this->actingAs($customer)->get(route('my-order', ['id' => $order->id]));

        $response->assertRedirect(route('user-chats.show', ['order' => $order]));
    }

    protected function makeOrder(User $customer, ?User $booster = null, array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'user_id' => $customer->id,
            'booster_id' => $booster?->id,
            'order_number' => (string) Str::orderedUuid(),
            'product' => 'Rank Boosting',
            'status' => 'InProgress',
            'payment_status' => 'paid',
            'price_cents' => 15000,
            'booster_payout_rate' => 0.6,
            'booster_payout_cents' => 9000,
            'currency' => 'USD',
            'details' => [
                'order' => [
                    'orderType' => 'Rank Boosting',
                    'currentDivision' => 'Gold I',
                    'desiredDivision' => 'Platinum I',
                ],
            ],
            'metadata' => [
                'customer' => [
                    'email' => $customer->email,
                ],
            ],
            'contact_method' => 'discord',
            'whatsapp' => '+15555555555',
            'discord' => 'customer#1234',
            'assigned_at' => $booster ? now() : null,
        ], $overrides));
    }

    protected function createChatMessage(Order $order, OrderChatThreadType $threadType, User $sender, string $body): OrderChatMessage
    {
        $thread = app(EnsureOrderChatThreads::class)->thread($order, $threadType);

        return $thread->messages()->create([
            'sender_id' => $sender->id,
            'sender_role' => (string) $sender->role,
            'sender_name' => $sender->name,
            'body' => $body,
        ]);
    }
}
