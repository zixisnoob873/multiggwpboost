<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderProgressPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_orders_start_with_zero_saved_progress_even_when_in_progress(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');

        $order = $this->makeRankBoostOrder($customer, $booster, OrderStatus::IN_PROGRESS);

        $this->assertSame(0, (int) data_get($order->details, 'progress.pct'));
        $this->assertSame('Iron III', data_get($order->details, 'progress.currentRank'));
        $this->assertSame(0, (int) data_get($order->details, 'progress.currentRR'));
        $this->assertSame(0, $order->progressPercent());
    }

    public function test_claiming_an_order_does_not_auto_initialize_progress_above_zero(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeRankBoostOrder($customer, null, OrderStatus::PENDING);

        $this->actingAs($booster)
            ->withSession([
                'booster_claim_captcha_codes' => [$order->id => '1234'],
            ])
            ->post(route('booster-claim-orders.claim', $order), [
                'claim_captcha' => '1234',
            ])
            ->assertRedirect(route('booster-claim-orders'));

        $order->refresh();

        $this->assertSame(OrderStatus::IN_PROGRESS, $order->status);
        $this->assertSame(0, (int) data_get($order->details, 'progress.pct'));
        $this->assertSame(0, $order->progressPercent());
    }

    public function test_progress_changes_only_when_saved_and_can_move_up_and_down(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $admin = $this->makeUser('admin');
        $order = $this->makeRankBoostOrder($customer, $booster, OrderStatus::IN_PROGRESS);

        $this->assertSame(0, (int) data_get($order->details, 'progress.pct'));

        $this->actingAs($booster)
            ->patch(route('orders.progress.update', $order), [
                'current_rank' => 'Bronze III',
                'current_rr' => 0,
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(50, (int) data_get($order->details, 'progress.pct'));
        $this->assertSame('Bronze III', data_get($order->details, 'progress.currentRank'));

        $this->actingAs($admin)
            ->patch(route('orders.progress.update', $order), [
                'current_rank' => 'Iron III',
                'current_rr' => 0,
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(0, (int) data_get($order->details, 'progress.pct'));
        $this->assertSame('Iron III', data_get($order->details, 'progress.currentRank'));
        $this->assertSame($admin->name, data_get($order->details, 'progress.updatedBy'));
    }

    public function test_invalid_progress_values_are_rejected(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $rankBoostOrder = $this->makeRankBoostOrder($customer, $booster, OrderStatus::IN_PROGRESS);
        $rankedWinsOrder = $this->makeRankedWinsOrder($customer, $booster);

        $this->actingAs($booster)
            ->from(route('booster-chats.show', ['order' => $rankBoostOrder]))
            ->patch(route('orders.progress.update', $rankBoostOrder), [
                'current_rank' => 'Radiant',
                'current_rr' => 101,
            ])
            ->assertRedirect(route('booster-chats.show', ['order' => $rankBoostOrder]))
            ->assertSessionHasErrors('current_rr');

        $this->actingAs($booster)
            ->from(route('booster-chats.show', ['order' => $rankedWinsOrder]))
            ->patch(route('orders.progress.update', $rankedWinsOrder), [
                'completed_wins' => 11,
            ])
            ->assertRedirect(route('booster-chats.show', ['order' => $rankedWinsOrder]))
            ->assertSessionHasErrors('completed_wins');
    }

    public function test_customer_and_booster_progress_views_render_zero_before_any_saved_update(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeRankBoostOrder($customer, $booster, OrderStatus::IN_PROGRESS);

        $this->actingAs($customer)
            ->get(route('user-chats.show', ['order' => $order]))
            ->assertOk()
            ->assertSee('data-order-bind="progressPct">0%</strong>', false)
            ->assertDontSee('data-order-bind="progressPct">50%</strong>', false);

        $this->actingAs($booster)
            ->get(route('booster-chats.show', ['order' => $order]))
            ->assertOk()
            ->assertSee('data-order-bind="progressPct">0%</strong>', false)
            ->assertDontSee('data-order-bind="progressPct">50%</strong>', false);
    }

    protected function makeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'account_status' => 'active',
        ]);
    }

    protected function makeRankBoostOrder(User $customer, ?User $booster, string $status): Order
    {
        return Order::query()->create([
            'user_id' => $customer->id,
            'booster_id' => $booster?->id,
            'order_number' => (string) Str::orderedUuid(),
            'product' => 'Rank Boosting',
            'status' => $status,
            'payment_status' => 'paid',
            'price_cents' => 15000,
            'booster_payout_rate' => 0.6,
            'booster_payout_cents' => 9000,
            'currency' => 'USD',
            'details' => [
                'order' => [
                    'orderType' => 'Rank Boosting',
                    'currentDivision' => 'Iron III',
                    'desiredDivision' => 'Silver III',
                    'currentRR' => 0,
                ],
            ],
            'metadata' => [
                'customer' => [
                    'email' => $customer->email,
                ],
            ],
            'contact_method' => 'discord',
            'discord' => 'customer#1234',
            'assigned_at' => $booster ? now() : null,
            'paid_at' => now(),
        ]);
    }

    protected function makeRankedWinsOrder(User $customer, ?User $booster): Order
    {
        return Order::query()->create([
            'user_id' => $customer->id,
            'booster_id' => $booster?->id,
            'order_number' => (string) Str::orderedUuid(),
            'product' => 'Ranked Wins',
            'status' => OrderStatus::IN_PROGRESS,
            'payment_status' => 'paid',
            'price_cents' => 11000,
            'booster_payout_rate' => 0.6,
            'booster_payout_cents' => 6600,
            'currency' => 'USD',
            'details' => [
                'order' => [
                    'orderType' => 'Ranked Wins',
                    'currentDivision' => 'Gold I',
                    'desiredDivision' => '10 Wins',
                    'numberOfWins' => 10,
                ],
            ],
            'metadata' => [
                'customer' => [
                    'email' => $customer->email,
                ],
            ],
            'contact_method' => 'discord',
            'discord' => 'customer#1234',
            'assigned_at' => $booster ? now() : null,
            'paid_at' => now(),
        ]);
    }
}
