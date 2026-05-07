<?php

namespace Tests\Feature;

use App\Actions\Admin\UpdateOrderAction;
use App\Actions\CompleteBoosterOrderAction;
use App\Data\Payments\PaymentCheckoutData;
use App\Enums\CustomerOrderEmailType;
use App\Jobs\SendCustomerOrderEmailJob;
use App\Mail\CustomerOrders\OrderPausedCustomerMail;
use App\Models\CustomerOrderEmailDispatch;
use App\Models\Order;
use App\Models\User;
use App\Services\Mail\CustomerOrderEmailNotifier;
use App\Services\OrderAssignmentService;
use App\Services\Orders\RankTrackerActionService;
use App\Services\Payments\FinalizePendingCheckoutService;
use App\Services\Payments\PendingCheckoutStore;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class CustomerOrderEmailNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_finalizing_the_same_checkout_only_queues_one_created_customer_email_dispatch(): void
    {
        Queue::fake();

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
                'region' => 'EU',
                'platform' => 'PC',
            ],
            paymentMethod: 'stripe',
            priceCents: 3299,
            total: 32.99,
            subtotal: 32.99,
        );

        $pendingCheckout = app(PendingCheckoutStore::class)->create($customer->id, $checkoutData);
        $finalizePendingCheckoutService = app(FinalizePendingCheckoutService::class);

        $firstOrder = $finalizePendingCheckoutService->finalize($pendingCheckout, 'stripe', [
            'payment_status' => 'paid',
            'payment_reference' => 'pi_test_created_email',
            'stripe_session_id' => 'cs_test_created_email',
            'paid_at' => now(),
        ]);

        $secondOrder = $finalizePendingCheckoutService->finalize($pendingCheckout, 'stripe', [
            'payment_status' => 'paid',
            'payment_reference' => 'pi_test_created_email',
            'stripe_session_id' => 'cs_test_created_email',
            'paid_at' => now(),
        ]);

        $this->assertSame($firstOrder->id, $secondOrder->id);
        $this->assertSame(1, Order::query()->count());
        $this->assertDatabaseCount('customer_order_email_dispatches', 1);
        $this->assertDatabaseHas('customer_order_email_dispatches', [
            'order_id' => $firstOrder->id,
            'email_type' => CustomerOrderEmailType::CREATED->value,
        ]);

        Queue::assertPushed(SendCustomerOrderEmailJob::class, 1);
    }

    public function test_order_created_email_dispatch_is_not_persisted_or_sent_when_order_creation_rolls_back(): void
    {
        Mail::fake();

        $customer = $this->makeUser('customer');

        try {
            DB::transaction(function () use ($customer): void {
                Order::query()->create([
                    'user_id' => $customer->id,
                    'order_number' => (string) Str::orderedUuid(),
                    'product' => 'Rank Boosting',
                    'status' => OrderStatus::PENDING,
                    'payment_status' => 'paid',
                    'price_cents' => 3299,
                    'original_price_cents' => 3299,
                    'discount_amount' => 0,
                    'booster_payout_rate' => Order::configuredBoosterPayoutPercentage(),
                    'booster_payout_basis_cents' => 3299,
                    'booster_payout_cents' => (int) round(3299 * Order::configuredBoosterPayoutRate()),
                    'currency' => 'USD',
                    'details' => [
                        'order' => [
                            'orderType' => 'Rank Boosting',
                            'currentDivision' => 'Silver I',
                            'desiredDivision' => 'Gold I',
                        ],
                    ],
                    'metadata' => [
                        'customer' => [
                            'firstName' => $customer->first_name,
                            'lastName' => $customer->last_name,
                            'email' => $customer->email,
                        ],
                    ],
                    'paid_at' => now(),
                ]);

                throw new RuntimeException('Force rollback after order creation.');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Force rollback after order creation.', $exception->getMessage());
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('customer_order_email_dispatches', 0);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_status_notifications_follow_real_status_transitions_and_ignore_non_status_updates(): void
    {
        Queue::fake();

        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeOrderWithoutEvents($customer, status: OrderStatus::PENDING);

        $claimedOrder = app(OrderAssignmentService::class)->claim($booster, $order);
        $claimedOrder->update([
            'payment_reference' => 'pi_non_status_update',
        ]);

        $pausedOrder = app(RankTrackerActionService::class)->pause($customer, $claimedOrder);
        $resumedOrder = app(RankTrackerActionService::class)->resume($customer, $pausedOrder);

        $resumedOrder->forceFill([
            'completion_proof_path' => 'completion/proof.png',
            'completion_proof_uploaded_at' => now(),
        ])->save();

        $completedOrder = app(CompleteBoosterOrderAction::class)->execute($booster, $resumedOrder->fresh());
        app(UpdateOrderAction::class)->execute($completedOrder, [
            'status' => OrderStatus::REFUNDED,
        ]);

        $this->assertDatabaseCount('customer_order_email_dispatches', 5);

        $emailTypes = CustomerOrderEmailDispatch::query()
            ->orderBy('id')
            ->pluck('email_type')
            ->all();

        $this->assertSame([
            CustomerOrderEmailType::ASSIGNED->value,
            CustomerOrderEmailType::PAUSED->value,
            CustomerOrderEmailType::RESUMED->value,
            CustomerOrderEmailType::COMPLETED->value,
            CustomerOrderEmailType::REFUNDED->value,
        ], $emailTypes);

        Queue::assertPushed(SendCustomerOrderEmailJob::class, 5);
    }

    public function test_send_customer_order_email_job_sends_the_expected_mailable_and_marks_dispatch_sent(): void
    {
        Queue::fake();
        Mail::fake();

        $customer = $this->makeUser('customer');
        $order = $this->makeOrderWithoutEvents($customer, status: OrderStatus::PAUSED, boosterId: null);

        $dispatch = app(CustomerOrderEmailNotifier::class)->queue(
            CustomerOrderEmailType::PAUSED,
            $order,
            [
                'previous_status' => OrderStatus::IN_PROGRESS,
                'current_status' => OrderStatus::PAUSED,
            ]
        );

        $this->assertNotNull($dispatch);

        (new SendCustomerOrderEmailJob($dispatch->id))->handle(app(CustomerOrderEmailNotifier::class));

        Mail::assertSent(OrderPausedCustomerMail::class, function (OrderPausedCustomerMail $mail) use ($order) {
            return data_get($mail->payload, 'order.number') === $order->order_number
                && data_get($mail->payload, 'customer.email') === $order->user->email;
        });

        $dispatch->refresh();

        $this->assertSame(CustomerOrderEmailDispatch::STATUS_SENT, $dispatch->status);
        $this->assertNotNull($dispatch->sent_at);
    }

    public function test_cancelling_an_order_queues_the_cancelled_customer_email_dispatch(): void
    {
        Queue::fake();

        $customer = $this->makeUser('customer');
        $order = $this->makeOrderWithoutEvents($customer, status: OrderStatus::PENDING);

        app(UpdateOrderAction::class)->execute($order, [
            'status' => OrderStatus::CANCELLED,
        ]);

        $this->assertDatabaseHas('customer_order_email_dispatches', [
            'order_id' => $order->id,
            'email_type' => CustomerOrderEmailType::CANCELLED->value,
        ]);

        Queue::assertPushed(SendCustomerOrderEmailJob::class, 1);
    }

    public function test_pause_and_refund_dispatch_payloads_include_reason_action_and_money_clarity(): void
    {
        Queue::fake();

        $customer = $this->makeUser('customer');
        $order = $this->makeOrderWithoutEvents($customer, status: OrderStatus::IN_PROGRESS);

        app(RankTrackerActionService::class)->pause($customer, $order);

        $pausedDispatch = CustomerOrderEmailDispatch::query()
            ->where('order_id', $order->id)
            ->where('email_type', CustomerOrderEmailType::PAUSED->value)
            ->firstOrFail();

        $this->assertSame('Paused from customer dashboard.', data_get($pausedDispatch->payload, 'order.lifecycle.reason'));
        $this->assertTrue(data_get($pausedDispatch->payload, 'order.lifecycle.customer_action_required'));

        app(UpdateOrderAction::class)->execute($order->fresh(), [
            'status' => OrderStatus::REFUNDED,
            'status_reason' => 'Customer requested a refund before work resumed.',
            'refund_amount' => '25.00',
            'refund_method' => 'Stripe',
            'refund_reference' => 're_123',
            'refund_arrival_estimate' => '5 business days',
        ]);

        $refundDispatch = CustomerOrderEmailDispatch::query()
            ->where('order_id', $order->id)
            ->where('email_type', CustomerOrderEmailType::REFUNDED->value)
            ->firstOrFail();

        $this->assertSame('Customer requested a refund before work resumed.', data_get($refundDispatch->payload, 'order.lifecycle.reason'));
        $this->assertSame(2500, data_get($refundDispatch->payload, 'order.refund.amount_cents'));
        $this->assertSame('$25.00', data_get($refundDispatch->payload, 'order.refund.amount_formatted'));
        $this->assertSame('Stripe', data_get($refundDispatch->payload, 'order.refund.method'));
        $this->assertSame('Original payment method', data_get($refundDispatch->payload, 'order.refund.destination'));
        $this->assertSame('5 business days', data_get($refundDispatch->payload, 'order.refund.estimated_arrival'));
        $this->assertSame('re_123', data_get($refundDispatch->payload, 'order.refund.reference'));
    }

    protected function makeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'account_status' => 'active',
        ]);
    }

    protected function makeOrderWithoutEvents(User $customer, string $status, ?int $boosterId = null): Order
    {
        return Order::withoutEvents(function () use ($boosterId, $customer, $status) {
            return Order::query()->create([
                'user_id' => $customer->id,
                'booster_id' => $boosterId,
                'order_number' => (string) Str::orderedUuid(),
                'product' => 'Rank Boosting',
                'status' => $status,
                'payment_status' => 'paid',
                'price_cents' => 3299,
                'original_price_cents' => 3299,
                'discount_amount' => 0,
                'booster_payout_rate' => Order::configuredBoosterPayoutPercentage(),
                'booster_payout_basis_cents' => 3299,
                'booster_payout_cents' => (int) round(3299 * Order::configuredBoosterPayoutRate()),
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
                        'addons' => ['Offline Mode'],
                    ],
                ],
                'metadata' => [
                    'customer' => [
                        'firstName' => $customer->first_name,
                        'lastName' => $customer->last_name,
                        'email' => $customer->email,
                    ],
                    'contactMethod' => 'email',
                    'paymentMethod' => 'stripe',
                    'paymentProvider' => 'stripe',
                ],
                'contact_method' => 'email',
                'paid_at' => now(),
                'assigned_at' => $boosterId ? now() : null,
            ])->load('user');
        });
    }
}
