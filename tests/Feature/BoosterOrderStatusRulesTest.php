<?php

namespace Tests\Feature;

use App\Actions\Admin\AssignBoosterToOrderAction;
use App\Actions\ClaimBoosterOrderAction;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderAssignmentService;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BoosterOrderStatusRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_booster_can_claim_pending_order_and_it_becomes_in_progress(): void
    {
        $booster = User::factory()->create([
            'role' => 'booster',
            'account_status' => 'active',
        ]);

        $order = $this->makeOrder(status: OrderStatus::PENDING);

        $response = $this->actingAs($booster)
            ->withSession([
                'booster_claim_captcha_codes' => [$order->id => '1234'],
            ])
            ->post(route('booster-claim-orders.claim', $order), [
                'claim_captcha' => '1234',
            ]);

        $response->assertRedirect(route('booster-claim-orders'));

        $order->refresh();

        $this->assertSame($booster->id, $order->booster_id);
        $this->assertSame(OrderStatus::IN_PROGRESS, $order->status);
        $this->assertNotNull($order->assigned_at);
    }

    public function test_only_one_booster_can_claim_the_same_order_when_two_stale_attempts_race(): void
    {
        $firstBooster = User::factory()->create([
            'role' => 'booster',
            'account_status' => 'active',
        ]);
        $secondBooster = User::factory()->create([
            'role' => 'booster',
            'account_status' => 'active',
        ]);

        $order = $this->makeOrder(status: OrderStatus::PENDING);
        $firstAttempt = Order::query()->findOrFail($order->id);
        $secondAttempt = Order::query()->findOrFail($order->id);

        $claimedOrder = app(ClaimBoosterOrderAction::class)->execute($firstBooster, $firstAttempt);

        $this->assertSame($firstBooster->id, $claimedOrder->booster_id);
        $this->assertSame(OrderStatus::IN_PROGRESS, $claimedOrder->status);

        try {
            app(ClaimBoosterOrderAction::class)->execute($secondBooster, $secondAttempt);
            $this->fail('Expected the second claim attempt to be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertSame('This order has already been claimed.', $exception->getMessage());
        }

        $order->refresh();

        $this->assertSame($firstBooster->id, $order->booster_id);
        $this->assertSame(OrderStatus::IN_PROGRESS, $order->status);
    }

    public function test_admin_assignment_prevents_a_stale_booster_claim_from_overwriting_it(): void
    {
        $adminAssignedBooster = User::factory()->create([
            'role' => 'booster',
            'account_status' => 'active',
        ]);
        $claimingBooster = User::factory()->create([
            'role' => 'booster',
            'account_status' => 'active',
        ]);

        $order = $this->makeOrder(status: OrderStatus::PENDING);
        $staleClaimAttempt = Order::query()->findOrFail($order->id);

        app(AssignBoosterToOrderAction::class)->execute($order, $adminAssignedBooster->id);

        try {
            app(ClaimBoosterOrderAction::class)->execute($claimingBooster, $staleClaimAttempt);
            $this->fail('Expected the stale booster claim to be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertSame('This order has already been claimed.', $exception->getMessage());
        }

        $order->refresh();

        $this->assertSame($adminAssignedBooster->id, $order->booster_id);
        $this->assertSame(OrderStatus::IN_PROGRESS, $order->status);
    }

    public function test_stale_booster_drop_cannot_unassign_order_after_admin_reassignment(): void
    {
        $firstBooster = User::factory()->create([
            'role' => 'booster',
            'account_status' => 'active',
        ]);
        $replacementBooster = User::factory()->create([
            'role' => 'booster',
            'account_status' => 'active',
        ]);

        $order = $this->makeOrder(
            status: OrderStatus::IN_PROGRESS,
            booster: $firstBooster,
        );
        $staleDropAttempt = Order::query()->findOrFail($order->id);

        app(AssignBoosterToOrderAction::class)->execute($order, $replacementBooster->id);

        try {
            app(OrderAssignmentService::class)->releaseToQueue($staleDropAttempt, $firstBooster);
            $this->fail('Expected the stale drop attempt to be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertSame('Only the assigned booster can drop this order.', $exception->getMessage());
        }

        $order->refresh();

        $this->assertSame($replacementBooster->id, $order->booster_id);
        $this->assertSame(OrderStatus::IN_PROGRESS, $order->status);
    }

    public function test_booster_cannot_claim_non_pending_orders(): void
    {
        $booster = User::factory()->create([
            'role' => 'booster',
            'account_status' => 'active',
        ]);

        foreach ([OrderStatus::IN_PROGRESS, OrderStatus::PAUSED, OrderStatus::COMPLETED, OrderStatus::CANCELLED] as $status) {
            $order = $this->makeOrder(status: $status);

            $response = $this->actingAs($booster)
                ->withSession([
                    'booster_claim_captcha_codes' => [$order->id => '1234'],
                ])
                ->post(route('booster-claim-orders.claim', $order), [
                    'claim_captcha' => '1234',
                ]);

            $response->assertRedirect(route('booster-claim-orders'));
            $response->assertSessionHasErrors('claim');

            $order->refresh();

            $this->assertNull($order->booster_id);
            $this->assertSame($status, $order->status);
        }
    }

    public function test_booster_can_start_an_assigned_pending_order_from_the_workspace_status_control(): void
    {
        $booster = User::factory()->create([
            'role' => 'booster',
            'account_status' => 'active',
        ]);

        $order = $this->makeOrder(
            status: OrderStatus::PENDING,
            booster: $booster,
        );

        $response = $this->actingAs($booster)
            ->from(route('booster-orders'))
            ->patch(route('booster-orders.status', $order), [
                'status' => OrderStatus::IN_PROGRESS,
            ]);

        $response->assertRedirect(route('booster-orders'));
        $order->refresh();
        $this->assertSame(OrderStatus::IN_PROGRESS, $order->status);
    }

    public function test_booster_cannot_use_the_status_endpoint_to_pause_or_complete_orders(): void
    {
        $booster = User::factory()->create([
            'role' => 'booster',
            'account_status' => 'active',
        ]);

        $order = $this->makeOrder(
            status: OrderStatus::IN_PROGRESS,
            booster: $booster,
        );

        $pauseResponse = $this->actingAs($booster)
            ->from(route('booster-orders'))
            ->patch(route('booster-orders.status', $order), [
                'status' => OrderStatus::PAUSED,
            ]);

        $pauseResponse->assertSessionHasErrors('status');
        $order->refresh();
        $this->assertSame(OrderStatus::IN_PROGRESS, $order->status);

        $completeResponse = $this->actingAs($booster)
            ->from(route('booster-orders'))
            ->patch(route('booster-orders.status', $order), [
                'status' => OrderStatus::COMPLETED,
            ]);

        $completeResponse->assertSessionHasErrors('status');
        $order->refresh();
        $this->assertSame(OrderStatus::IN_PROGRESS, $order->status);
    }

    public function test_booster_cannot_set_order_back_to_pending(): void
    {
        $booster = User::factory()->create([
            'role' => 'booster',
            'account_status' => 'active',
        ]);

        $order = $this->makeOrder(
            status: OrderStatus::IN_PROGRESS,
            booster: $booster,
        );

        $response = $this->actingAs($booster)
            ->from(route('booster-orders'))
            ->patch(route('booster-orders.status', $order), [
                'status' => OrderStatus::PENDING,
            ]);

        $response->assertSessionHasErrors('status');

        $order->refresh();
        $this->assertSame(OrderStatus::IN_PROGRESS, $order->status);
    }

    public function test_booster_cannot_open_completed_or_cancelled_orders(): void
    {
        $booster = User::factory()->create([
            'role' => 'booster',
            'account_status' => 'active',
        ]);

        foreach ([OrderStatus::COMPLETED, OrderStatus::CANCELLED] as $status) {
            $order = $this->makeOrder(
                status: $status,
                booster: $booster,
            );

            $pageResponse = $this->actingAs($booster)->get(route('booster-chats.show', $order));
            $pageResponse->assertRedirect(route('booster-orders', ['view' => 'all']));
            $pageResponse->assertSessionHasErrors('status');

            $progressResponse = $this->actingAs($booster)->patch(route('orders.progress.update', $order), [
                'current_rank' => 'Diamond I',
                'current_rr' => 10,
            ]);
            $progressResponse->assertForbidden();
        }
    }

    public function test_booster_orders_list_keeps_completed_orders_in_all_view_but_hides_them_from_assigned_view(): void
    {
        $booster = User::factory()->create([
            'role' => 'booster',
            'account_status' => 'active',
        ]);

        $activeOrder = $this->makeOrder(status: OrderStatus::IN_PROGRESS, booster: $booster);
        $completedOrder = $this->makeOrder(status: OrderStatus::COMPLETED, booster: $booster);

        $this->actingAs($booster)
            ->get(route('booster-orders', ['view' => 'all']))
            ->assertOk()
            ->assertSee($activeOrder->order_number)
            ->assertSee($completedOrder->order_number);

        $this->actingAs($booster)
            ->get(route('booster-orders', ['view' => 'assigned']))
            ->assertOk()
            ->assertSee($activeOrder->order_number)
            ->assertDontSee($completedOrder->order_number);
    }

    protected function makeOrder(string $status = OrderStatus::PENDING, ?User $booster = null): Order
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        return Order::query()->create([
            'user_id' => $customer->id,
            'booster_id' => $booster?->id,
            'order_number' => (string) Str::orderedUuid(),
            'product' => 'Rank Boosting',
            'status' => $status,
            'payment_status' => 'paid',
            'price_cents' => 15000,
            'booster_payout_rate' => 60,
            'booster_payout_cents' => 9000,
            'currency' => 'USD',
            'details' => [
                'order' => [
                    'orderType' => 'Rank Boosting',
                    'currentDivision' => 'Gold I',
                    'desiredDivision' => 'Platinum I',
                ],
            ],
            'assigned_at' => $booster ? now() : null,
        ]);
    }
}
