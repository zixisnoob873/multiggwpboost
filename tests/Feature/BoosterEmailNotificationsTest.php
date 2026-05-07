<?php

namespace Tests\Feature;

use App\Jobs\SendCustomerOrderEmailJob;
use App\Mail\Transactional\BoosterAssignedOrderMail;
use App\Mail\Transactional\WithdrawalApprovedMail;
use App\Mail\Transactional\WithdrawalRejectedMail;
use App\Models\CustomerOrderEmailDispatch;
use App\Models\Order;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class BoosterEmailNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_assigning_an_existing_order_queues_booster_assignment_email_once(): void
    {
        Mail::fake();
        Queue::fake();

        $admin = $this->makeAdmin();
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeOrder($customer, OrderStatus::PENDING);

        $this->actingAs($admin)
            ->from(route('admin-orders.edit', $order))
            ->patch(route('admin-orders.assign-booster', $order), [
                'booster_id' => $booster->id,
            ])
            ->assertRedirect();

        $order->refresh();

        $this->assertSame($booster->id, $order->booster_id);
        $this->assertSame(OrderStatus::IN_PROGRESS, $order->status);

        Mail::assertQueued(BoosterAssignedOrderMail::class, 1);
        Mail::assertQueued(BoosterAssignedOrderMail::class, function (BoosterAssignedOrderMail $mail) use ($order, $booster) {
            return data_get($mail->payload, 'booster.email') === $booster->email
                && data_get($mail->payload, 'order.number') === $order->order_number;
        });

        $this->assertDatabaseHas('customer_order_email_dispatches', [
            'order_id' => $order->id,
            'email_type' => 'order_assigned',
        ]);

        $this->actingAs($admin)
            ->from(route('admin-orders.edit', $order))
            ->patch(route('admin-orders.assign-booster', $order), [
                'booster_id' => $booster->id,
            ])
            ->assertRedirect();

        Mail::assertQueued(BoosterAssignedOrderMail::class, 1);
        $this->assertSame(1, CustomerOrderEmailDispatch::query()
            ->where('order_id', $order->id)
            ->where('email_type', 'order_assigned')
            ->count());
        Queue::assertPushed(SendCustomerOrderEmailJob::class, 1);
    }

    public function test_admin_manual_order_with_booster_defaults_to_in_progress_and_queues_expected_emails(): void
    {
        Http::fake();
        Mail::fake();
        Queue::fake();

        $admin = $this->makeAdmin();
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');

        $this->actingAs($admin)->post(route('admin-orders.store-manual'), [
            'user_id' => $customer->id,
            'booster_id' => $booster->id,
            'product' => 'Rank Boosting',
            'game' => 'Valorant',
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
        ])->assertRedirect(route('admin-custom-order'));

        $order = Order::query()->firstOrFail();

        $this->assertSame(OrderStatus::IN_PROGRESS, $order->status);
        $this->assertSame($booster->id, $order->booster_id);
        $this->assertNotNull($order->assigned_at);

        $this->assertDatabaseHas('customer_order_email_dispatches', [
            'order_id' => $order->id,
            'email_type' => 'order_created',
        ]);
        $this->assertDatabaseHas('customer_order_email_dispatches', [
            'order_id' => $order->id,
            'email_type' => 'order_assigned',
        ]);

        Mail::assertQueued(BoosterAssignedOrderMail::class, 1);
        Queue::assertPushed(SendCustomerOrderEmailJob::class, 2);
    }

    public function test_admin_manual_order_without_booster_stays_pending_and_does_not_send_assignment_email(): void
    {
        Http::fake();
        Mail::fake();
        Queue::fake();

        $admin = $this->makeAdmin();
        $customer = $this->makeUser('customer');

        $this->actingAs($admin)->post(route('admin-orders.store-manual'), [
            'user_id' => $customer->id,
            'product' => 'Rank Boosting',
            'game' => 'Valorant',
            'payment_status' => 'paid',
            'price' => '149.99',
            'currency' => 'usd',
            'current_division' => 'Gold II',
            'desired_division' => 'Diamond I',
            'average_rr' => '18 OR LOWER',
            'region' => 'NA',
            'platform' => 'PC',
            'account_type' => 'Account Shared',
        ])->assertRedirect(route('admin-custom-order'));

        $order = Order::query()->firstOrFail();

        $this->assertSame(OrderStatus::PENDING, $order->status);
        $this->assertNull($order->booster_id);
        $this->assertNull($order->assigned_at);

        $this->assertDatabaseHas('customer_order_email_dispatches', [
            'order_id' => $order->id,
            'email_type' => 'order_created',
            'recipient_email' => $customer->email,
        ]);
        $this->assertSame(0, CustomerOrderEmailDispatch::query()
            ->where('order_id', $order->id)
            ->where('email_type', 'order_assigned')
            ->count());

        Mail::assertNotQueued(BoosterAssignedOrderMail::class);
        Queue::assertPushed(SendCustomerOrderEmailJob::class, 1);
    }

    public function test_withdrawal_approval_email_only_sends_once_for_real_transition(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        $booster = $this->makeUser('booster');
        $withdrawalRequest = WithdrawalRequest::query()->create([
            'booster_id' => $booster->id,
            'amount_cents' => 12500,
            'status' => WithdrawalRequest::STATUS_PENDING,
        ]);

        $this->actingAs($admin)->patch(route('admin-withdrawal-requests.update', $withdrawalRequest), [
            'status' => WithdrawalRequest::STATUS_APPROVED,
            'payout_method' => 'Wise',
            'transaction_reference' => 'WISE-123',
            'estimated_arrival' => '1-2 business days',
        ])->assertRedirect(route('admin-withdrawal-requests.index'));

        $withdrawalRequest->refresh();
        $this->assertSame(WithdrawalRequest::STATUS_APPROVED, $withdrawalRequest->status);

        Mail::assertQueued(WithdrawalApprovedMail::class, 1);
        Mail::assertQueued(WithdrawalApprovedMail::class, function (WithdrawalApprovedMail $mail) use ($booster) {
            return data_get($mail->payload, 'booster.email') === $booster->email
                && data_get($mail->payload, 'withdrawal.amount_cents') === 12500
                && data_get($mail->payload, 'withdrawal.payout_method') === 'Wise'
                && data_get($mail->payload, 'withdrawal.transaction_reference') === 'WISE-123'
                && data_get($mail->payload, 'withdrawal.estimated_arrival') === '1-2 business days';
        });

        $this->actingAs($admin)->patch(route('admin-withdrawal-requests.update', $withdrawalRequest), [
            'status' => WithdrawalRequest::STATUS_REJECTED,
            'notes' => 'Payout account could not be verified.',
        ])->assertRedirect(route('admin-withdrawal-requests.index'));

        Mail::assertQueued(WithdrawalApprovedMail::class, 1);
        Mail::assertNotQueued(WithdrawalRejectedMail::class);
    }

    public function test_withdrawal_rejection_queues_rejected_email(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        $booster = $this->makeUser('booster');
        $withdrawalRequest = WithdrawalRequest::query()->create([
            'booster_id' => $booster->id,
            'amount_cents' => 9800,
            'status' => WithdrawalRequest::STATUS_PENDING,
        ]);

        $this->actingAs($admin)->patch(route('admin-withdrawal-requests.update', $withdrawalRequest), [
            'status' => WithdrawalRequest::STATUS_REJECTED,
            'notes' => 'Payout account could not be verified.',
        ])->assertRedirect(route('admin-withdrawal-requests.index'));

        Mail::assertQueued(WithdrawalRejectedMail::class, 1);
        Mail::assertQueued(WithdrawalRejectedMail::class, function (WithdrawalRejectedMail $mail) use ($booster) {
            return data_get($mail->payload, 'booster.email') === $booster->email
                && data_get($mail->payload, 'withdrawal.amount_cents') === 9800
                && data_get($mail->payload, 'withdrawal.rejection_reason') === 'Payout account could not be verified.'
                && data_get($mail->payload, 'withdrawal.can_resubmit') === true;
        });
    }

    protected function makeAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
    }

    protected function makeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'account_status' => 'active',
        ]);
    }

    protected function makeOrder(User $customer, string $status): Order
    {
        return Order::withoutEvents(function () use ($customer, $status) {
            return Order::query()->create([
                'user_id' => $customer->id,
                'order_number' => (string) Str::orderedUuid(),
                'product' => 'Rank Boosting',
                'status' => $status,
                'payment_status' => 'paid',
                'price_cents' => 3299,
                'original_price_cents' => 3299,
                'discount_amount' => 0,
                'booster_payout_rate' => 60,
                'booster_payout_cents' => 1979,
                'booster_payout_basis_cents' => 3299,
                'currency' => 'USD',
                'details' => [
                    'service' => 'Rank Boosting',
                    'from' => 'Silver I',
                    'to' => 'Gold I',
                    'region' => 'EU',
                    'addons' => ['Offline Mode'],
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
                        'firstName' => $customer->first_name,
                        'lastName' => $customer->last_name,
                        'email' => $customer->email,
                    ],
                ],
                'contact_method' => 'email',
                'paid_at' => now(),
            ]);
        });
    }
}
