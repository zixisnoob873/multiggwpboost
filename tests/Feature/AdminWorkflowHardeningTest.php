<?php

namespace Tests\Feature;

use App\Models\BoosterApplication;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminWorkflowHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_order_filters_can_target_customer_and_booster(): void
    {
        $admin = $this->makeAdmin();
        $customerA = $this->makeUser('customer', ['name' => 'Target Customer', 'email' => 'target@example.com']);
        $customerB = $this->makeUser('customer', ['name' => 'Other Customer', 'email' => 'other@example.com']);
        $boosterA = $this->makeUser('booster', ['name' => 'Target Booster', 'nickname' => 'TargetBoost', 'email' => 'booster-target@example.com']);
        $boosterB = $this->makeUser('booster', ['name' => 'Other Booster', 'nickname' => 'OtherBoost', 'email' => 'booster-other@example.com']);
        $matchingOrder = $this->makeOrder($customerA, $boosterA);
        $otherOrder = $this->makeOrder($customerB, $boosterB);

        $this->actingAs($admin)
            ->get(route('admin-total-order', [
                'customer_id' => $customerA->id,
                'booster_id' => $boosterA->id,
            ]))
            ->assertOk()
            ->assertSee($matchingOrder->order_number)
            ->assertDontSee($otherOrder->order_number);
    }

    public function test_refund_requires_paid_order(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeOrder($customer, $booster, [
            'payment_status' => 'pending',
            'status' => OrderStatus::IN_PROGRESS,
            'paid_at' => null,
        ]);

        $this->actingAs($admin)
            ->from(route('admin-total-order'))
            ->patch(route('admin-orders.status', $order), [
                'status' => OrderStatus::REFUNDED,
            ])
            ->assertRedirect(route('admin-total-order'))
            ->assertSessionHasErrors('status');

        $this->assertSame(OrderStatus::IN_PROGRESS, $order->fresh()->status);
    }

    public function test_customer_cannot_update_order_status_via_admin_operations_route(): void
    {
        $customerActor = $this->makeUser('customer');
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeOrder($customer, $booster);

        $this->actingAs($customerActor)
            ->patch(route('admin-orders.status', $order), [
                'status' => OrderStatus::PAUSED,
            ])
            ->assertForbidden();
    }

    public function test_contact_message_status_update_preserves_links_and_audits_change(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeUser('customer');
        $relatedOrder = $this->makeOrder($customer);
        $assignedAdmin = $this->makeAdmin();
        $message = ContactMessage::query()->create([
            'name' => 'Inbox Sender',
            'email' => 'sender@example.com',
            'order_ref' => 'REF-123',
            'message' => 'Need an order update.',
            'status' => ContactMessage::STATUS_NEW,
            'assigned_admin_id' => $assignedAdmin->id,
            'related_order_id' => $relatedOrder->id,
            'related_customer_id' => $customer->id,
            'internal_notes' => 'Keep linked records intact.',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin-contact-messages.update', $message), [
                'status' => ContactMessage::STATUS_READ,
            ])
            ->assertRedirect(route('admin-contact-messages.edit', $message));

        $message->refresh();

        $this->assertSame(ContactMessage::STATUS_READ, $message->status);
        $this->assertSame($assignedAdmin->id, $message->assigned_admin_id);
        $this->assertSame($relatedOrder->id, $message->related_order_id);
        $this->assertSame($customer->id, $message->related_customer_id);
        $this->assertSame('Keep linked records intact.', $message->internal_notes);

        $this->assertDatabaseHas('admin_audit_logs', [
            'module' => 'people',
            'action' => 'contact_message_updated',
            'subject_id' => $message->id,
        ]);
    }

    public function test_contact_message_invalid_transition_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $message = ContactMessage::query()->create([
            'name' => 'Resolved Sender',
            'email' => 'resolved@example.com',
            'message' => 'Already handled.',
            'status' => ContactMessage::STATUS_REPLIED,
        ]);

        $this->actingAs($admin)
            ->from(route('admin-contact-messages.edit', $message))
            ->patch(route('admin-contact-messages.update', $message), [
                'status' => ContactMessage::STATUS_NEW,
            ])
            ->assertRedirect(route('admin-contact-messages.edit', $message))
            ->assertSessionHasErrors('status');

        $this->assertSame(ContactMessage::STATUS_REPLIED, $message->fresh()->status);
    }

    public function test_contact_message_edit_page_removes_legacy_workflow_and_quick_links(): void
    {
        $admin = $this->makeAdmin();
        $message = ContactMessage::query()->create([
            'name' => 'UI Sender',
            'email' => 'ui@example.com',
            'message' => 'Please follow up.',
            'status' => ContactMessage::STATUS_NEW,
        ]);

        $this->actingAs($admin)
            ->get(route('admin-contact-messages.edit', $message))
            ->assertOk()
            ->assertSee('Handling')
            ->assertSee('Read')
            ->assertSee('Replied')
            ->assertSee('Ignored')
            ->assertDontSee('Workflow')
            ->assertDontSee('Quick Links');
    }

    public function test_booster_application_convert_route_redirects_to_existing_booster_edit(): void
    {
        $admin = $this->makeAdmin();
        $booster = $this->makeUser('booster', [
            'name' => 'Converted Booster',
            'nickname' => 'ConvertedHero',
            'email' => 'converted@example.com',
        ]);
        $application = $this->makeBoosterApplication([
            'status' => BoosterApplication::STATUS_HIRED,
            'converted_booster_id' => $booster->id,
            'converted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin-booster-applications.convert', $application))
            ->assertRedirect(route('admin-boosters.edit', ['booster' => $booster->nickname]));
    }

    public function test_booster_application_cannot_be_marked_hired_without_conversion(): void
    {
        $admin = $this->makeAdmin();
        $application = $this->makeBoosterApplication([
            'status' => BoosterApplication::STATUS_APPROVED,
        ]);

        $this->actingAs($admin)
            ->from(route('admin-booster-applications.edit', $application))
            ->patch(route('admin-booster-applications.update', $application), [
                'status' => BoosterApplication::STATUS_HIRED,
                'admin_notes' => 'Trying to force hired state.',
            ])
            ->assertRedirect(route('admin-booster-applications.edit', $application))
            ->assertSessionHasErrors('status');

        $this->assertSame(BoosterApplication::STATUS_APPROVED, $application->fresh()->status);
    }

    public function test_booster_application_edit_page_removes_legacy_workflow_copy(): void
    {
        $admin = $this->makeAdmin();
        $application = $this->makeBoosterApplication();

        $this->actingAs($admin)
            ->get(route('admin-booster-applications.edit', $application))
            ->assertOk()
            ->assertSee('Handling')
            ->assertDontSee('Workflow');
    }

    public function test_withdrawal_index_defaults_to_pending_status_view(): void
    {
        $admin = $this->makeAdmin();
        $pendingBooster = $this->makeUser('booster', ['name' => 'Pending Booster', 'nickname' => 'PendingBoost']);
        $approvedBooster = $this->makeUser('booster', ['name' => 'Approved Booster', 'nickname' => 'ApprovedBoost']);

        $this->makeWithdrawalRequest($pendingBooster, [
            'status' => WithdrawalRequest::STATUS_PENDING,
            'amount_cents' => 5000,
        ]);
        $this->makeWithdrawalRequest($approvedBooster, [
            'status' => WithdrawalRequest::STATUS_APPROVED,
            'amount_cents' => 7000,
            'processed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin-withdrawal-requests.index'))
            ->assertOk()
            ->assertSee('Pending Booster')
            ->assertDontSee('Approved Booster');
    }

    public function test_withdrawal_index_can_filter_approved_status_view(): void
    {
        $admin = $this->makeAdmin();
        $pendingBooster = $this->makeUser('booster', ['name' => 'Pending Queue', 'nickname' => 'PendingQueue']);
        $approvedBooster = $this->makeUser('booster', ['name' => 'Approved Queue', 'nickname' => 'ApprovedQueue']);

        $this->makeWithdrawalRequest($pendingBooster, [
            'status' => WithdrawalRequest::STATUS_PENDING,
            'amount_cents' => 5000,
        ]);
        $this->makeWithdrawalRequest($approvedBooster, [
            'status' => WithdrawalRequest::STATUS_APPROVED,
            'amount_cents' => 7000,
            'processed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin-withdrawal-requests.index', ['status' => WithdrawalRequest::STATUS_APPROVED]))
            ->assertOk()
            ->assertSee('Approved Queue')
            ->assertDontSee('Pending Queue');
    }

    public function test_processed_withdrawal_request_cannot_transition_again_via_admin_route(): void
    {
        $admin = $this->makeAdmin();
        $booster = $this->makeUser('booster');
        $withdrawalRequest = $this->makeWithdrawalRequest($booster, [
            'status' => WithdrawalRequest::STATUS_APPROVED,
            'processed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin-withdrawal-requests.update', $withdrawalRequest), [
                'status' => WithdrawalRequest::STATUS_REJECTED,
            ])
            ->assertRedirect(route('admin-withdrawal-requests.index'))
            ->assertSessionHas('status', 'Withdrawal request was already processed.');

        $this->assertSame(WithdrawalRequest::STATUS_APPROVED, $withdrawalRequest->fresh()->status);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    protected function makeAdmin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'account_status' => 'active',
        ]);
    }

    protected function makeUser(string $role, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'account_status' => 'active',
        ], $overrides));
    }

    protected function makeOrder(User $customer, ?User $booster = null, array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'user_id' => $customer->id,
            'booster_id' => $booster?->id,
            'order_number' => (string) Str::orderedUuid(),
            'product' => 'Rank Boosting',
            'status' => OrderStatus::IN_PROGRESS,
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
            'metadata' => [
                'customer' => [
                    'email' => $customer->email,
                ],
            ],
            'contact_method' => 'discord',
            'discord' => 'customer#1234',
            'assigned_at' => $booster ? now() : null,
        ], $overrides));
    }

    protected function makeBoosterApplication(array $overrides = []): BoosterApplication
    {
        return BoosterApplication::query()->create(array_merge([
            'name' => 'Applicant Example',
            'nickname' => 'ApplicantOne',
            'email' => 'applicant@example.com',
            'current_rank' => 'Immortal I',
            'peak_rank' => 'Radiant',
            'average_time' => '4 hours',
            'discord' => 'applicant#1234',
            'main_account_tracker' => 'https://tracker.example.test/player',
            'marketplace_profile' => 'https://market.example.test/player',
            'regions' => ['NA'],
            'status' => BoosterApplication::STATUS_NEW,
        ], $overrides));
    }

    protected function makeWithdrawalRequest(User $booster, array $overrides = []): WithdrawalRequest
    {
        return WithdrawalRequest::query()->create(array_merge([
            'booster_id' => $booster->id,
            'amount_cents' => 5000,
            'status' => WithdrawalRequest::STATUS_PENDING,
            'notes' => null,
            'processed_at' => null,
        ], $overrides));
    }
}
