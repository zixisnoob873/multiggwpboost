<?php

namespace Tests\Feature;

use App\Enums\CustomerOrderEmailType;
use App\Mail\Transactional\AccountCreatedMail;
use App\Mail\Transactional\AccountReactivatedMail;
use App\Mail\Transactional\AccountSuspendedMail;
use App\Mail\Transactional\BoosterAssignedOrderMail;
use App\Mail\Transactional\PasswordResetMail;
use App\Mail\Transactional\WithdrawalApprovedMail;
use App\Mail\Transactional\WithdrawalRejectedMail;
use App\Models\Order;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\Mail\AccountLifecycleEmailNotifier;
use App\Services\Mail\BoosterEmailNotifier;
use App\Services\Mail\CustomerOrderEmailNotifier;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmailTemplateRenderingTest extends TestCase
{
    use RefreshDatabase;

    private string $logoUrl = 'https://cdn.example.test/assets/ggwp-logo.png';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.name' => 'GGWP Boost',
            'mail.logo_url' => $this->logoUrl,
            'footer.support.email' => 'support@example.test',
            'footer.support.community_url' => 'https://discord.gg/example',
        ]);
    }

    public function test_customer_order_email_templates_render_with_real_dispatch_payloads(): void
    {
        Queue::fake();

        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeOrder($customer, OrderStatus::COMPLETED, $booster->id);
        $notifier = app(CustomerOrderEmailNotifier::class);

        $contexts = [
            CustomerOrderEmailType::ASSIGNED->value => [
                'previous_status' => OrderStatus::PENDING,
                'current_status' => OrderStatus::IN_PROGRESS,
            ],
            CustomerOrderEmailType::PAUSED->value => [
                'previous_status' => OrderStatus::IN_PROGRESS,
                'current_status' => OrderStatus::PAUSED,
            ],
            CustomerOrderEmailType::RESUMED->value => [
                'previous_status' => OrderStatus::PAUSED,
                'current_status' => OrderStatus::IN_PROGRESS,
            ],
        ];

        foreach (CustomerOrderEmailType::cases() as $type) {
            $dispatch = $notifier->queue($type, $order, $contexts[$type->value] ?? []);

            $this->assertNotNull($dispatch);
            $this->assertPayloadHasKeys((array) $dispatch->payload, [
                'branding.app_name',
                'branding.logo_url',
                'links.order_url',
                'links.orders_url',
                'links.support_url',
                'links.support_email',
                'links.community_url',
                'customer.name',
                'customer.email',
                'order.id',
                'order.number',
                'order.service_name',
                'order.status_label',
                'order.price_formatted',
            ]);

            $html = $notifier->makeMailable($dispatch)->render();

            $this->assertStringContainsString($this->logoUrl, $html);
            $this->assertStringContainsString($order->order_number, $html);
        }
    }

    public function test_transactional_email_templates_render_with_real_notifier_payloads(): void
    {
        Mail::fake();

        $accountNotifier = app(AccountLifecycleEmailNotifier::class);
        $boosterNotifier = app(BoosterEmailNotifier::class);

        $createdUser = $this->makeUser('customer');
        $accountNotifier->queueAccountCreated($createdUser, 'self-service');

        $suspendedUser = $this->makeUser('customer');
        $suspendedUser->forceFill(['account_status' => 'suspended'])->save();
        $accountNotifier->queueStatusChanged($suspendedUser, 'active');

        $reactivatedUser = $this->makeUser('booster');
        $reactivatedUser->forceFill(['account_status' => 'active'])->save();
        $accountNotifier->queueStatusChanged($reactivatedUser, 'suspended');

        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeOrder($customer, OrderStatus::IN_PROGRESS, $booster->id);
        $boosterNotifier->queueOrderAssignedByAdmin($order);

        $approvedWithdrawal = $this->makeWithdrawalRequest($booster, WithdrawalRequest::STATUS_APPROVED, 12500);
        $boosterNotifier->queueWithdrawalProcessed($approvedWithdrawal);

        $rejectedWithdrawal = $this->makeWithdrawalRequest($booster, WithdrawalRequest::STATUS_REJECTED, 9800);
        $boosterNotifier->queueWithdrawalProcessed($rejectedWithdrawal);

        $resetUser = $this->makeUser('customer');
        $resetUser->sendPasswordResetNotification('test-reset-token');

        $this->assertQueuedRenderable(AccountCreatedMail::class, [
            'branding.app_name',
            'branding.logo_url',
            'links.login_url',
            'links.dashboard_url',
            'links.support_url',
            'links.support_email',
            'user.id',
            'user.name',
            'user.email',
            'user.role_label',
            'account.source',
        ], [$createdUser->email, 'Your account is ready']);

        $this->assertQueuedRenderable(AccountSuspendedMail::class, [
            'branding.app_name',
            'branding.logo_url',
            'links.support_url',
            'links.support_email',
            'user.id',
            'user.name',
            'user.email',
            'user.role_label',
            'account.previous_status',
            'account.status',
        ], [$suspendedUser->email, 'Your account has been suspended']);

        $this->assertQueuedRenderable(AccountReactivatedMail::class, [
            'branding.app_name',
            'branding.logo_url',
            'links.login_url',
            'links.dashboard_url',
            'links.support_url',
            'links.support_email',
            'user.id',
            'user.name',
            'user.email',
            'user.role_label',
            'account.previous_status',
            'account.status',
        ], [$reactivatedUser->email, 'Your account has been reactivated']);

        $this->assertQueuedRenderable(BoosterAssignedOrderMail::class, [
            'branding.app_name',
            'branding.logo_url',
            'links.order_url',
            'links.orders_url',
            'links.dashboard_url',
            'links.support_url',
            'links.support_email',
            'booster.name',
            'booster.email',
            'customer.name',
            'order.id',
            'order.number',
            'order.service_name',
            'order.status_label',
            'order.task_label',
        ], [$order->order_number, 'A new order has been assigned to you']);

        $this->assertQueuedRenderable(WithdrawalApprovedMail::class, [
            'branding.app_name',
            'branding.logo_url',
            'links.wallet_url',
            'links.support_url',
            'links.support_email',
            'booster.name',
            'booster.email',
            'withdrawal.id',
            'withdrawal.status',
            'withdrawal.amount_cents',
            'withdrawal.amount_formatted',
        ], ['Your withdrawal request has been approved', '$125.00']);

        $this->assertQueuedRenderable(WithdrawalRejectedMail::class, [
            'branding.app_name',
            'branding.logo_url',
            'links.wallet_url',
            'links.support_url',
            'links.support_email',
            'booster.name',
            'booster.email',
            'withdrawal.id',
            'withdrawal.status',
            'withdrawal.amount_cents',
            'withdrawal.amount_formatted',
        ], ['Your withdrawal request was rejected', '$98.00']);

        $this->assertQueuedRenderable(PasswordResetMail::class, [
            'branding.app_name',
            'branding.logo_url',
            'links.login_url',
            'links.support_url',
            'links.support_email',
            'user.id',
            'user.name',
            'user.email',
            'reset.token',
            'reset.url',
            'reset.expires_in_minutes',
        ], [$resetUser->email, 'Reset your password']);
    }

    /**
     * @param  class-string<Mailable>  $mailClass
     * @param  array<int, string>  $requiredPayloadKeys
     * @param  array<int, string>  $expectedHtml
     */
    private function assertQueuedRenderable(string $mailClass, array $requiredPayloadKeys, array $expectedHtml): void
    {
        Mail::assertQueued($mailClass, function (Mailable $mail) use ($requiredPayloadKeys, $expectedHtml): bool {
            $payload = (array) $mail->payload;

            $this->assertPayloadHasKeys($payload, $requiredPayloadKeys);

            $html = $mail->render();

            $this->assertStringContainsString($this->logoUrl, $html);

            foreach ($expectedHtml as $text) {
                $this->assertStringContainsString($text, $html);
            }

            return true;
        });
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function assertPayloadHasKeys(array $payload, array $keys): void
    {
        foreach ($keys as $key) {
            $this->assertTrue(Arr::has($payload, $key), "Expected email payload to contain [{$key}].");
        }
    }

    private function makeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'account_status' => 'active',
        ]);
    }

    private function makeOrder(User $customer, string $status, ?int $boosterId = null): Order
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
                ],
                'contact_method' => 'email',
                'paid_at' => now(),
                'assigned_at' => $boosterId ? now() : null,
                'completed_at' => $status === OrderStatus::COMPLETED ? now() : null,
            ])->load(['booster', 'user']);
        });
    }

    private function makeWithdrawalRequest(User $booster, string $status, int $amountCents): WithdrawalRequest
    {
        return WithdrawalRequest::query()->create([
            'booster_id' => $booster->id,
            'amount_cents' => $amountCents,
            'status' => $status,
            'processed_at' => now(),
        ])->load('booster');
    }
}
