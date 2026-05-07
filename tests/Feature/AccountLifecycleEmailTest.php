<?php

namespace Tests\Feature;

use App\Mail\Transactional\AccountCreatedMail;
use App\Mail\Transactional\AccountReactivatedMail;
use App\Mail\Transactional\AccountSuspendedMail;
use App\Models\User;
use App\Services\Mail\AccountLifecycleEmailNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class AccountLifecycleEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_service_registration_queues_account_created_email(): void
    {
        Mail::fake();

        $response = $this->post(route('signup.submit'), [
            'first_name' => 'Avery',
            'last_name' => 'Customer',
            'nickname' => 'Avery123',
            'email' => 'avery@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'accepted_terms' => '1',
        ]);

        $response->assertRedirect(route('customer-dashboard'));
        $this->assertAuthenticated();

        Mail::assertQueued(AccountCreatedMail::class, function (AccountCreatedMail $mail) {
            return data_get($mail->payload, 'user.email') === 'avery@example.com'
                && data_get($mail->payload, 'user.role_label') === 'Customer'
                && data_get($mail->payload, 'account.source') === 'self-service';
        });
    }

    public function test_admin_customer_and_booster_creation_queue_account_created_emails(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin-customers.store'), [
            'first_name' => 'Casey',
            'last_name' => 'Customer',
            'nickname' => 'CaseyCust1',
            'email' => 'casey.customer@example.com',
            'password' => 'SecurePass123!',
            'account_status' => 'active',
        ])->assertRedirect(route('admin-customers.index'));

        $this->actingAs($admin)->post(route('admin-boosters.store'), [
            'first_name' => 'Blake',
            'last_name' => 'Booster',
            'nickname' => 'BlakeBoost1',
            'email' => 'blake.booster@example.com',
            'password' => 'SecurePass123!',
            'account_status' => 'active',
        ])->assertRedirect(route('admin-boosters.index'));

        Mail::assertQueued(AccountCreatedMail::class, 2);
        Mail::assertQueued(AccountCreatedMail::class, function (AccountCreatedMail $mail) {
            return data_get($mail->payload, 'user.email') === 'casey.customer@example.com'
                && data_get($mail->payload, 'user.role_label') === 'Customer';
        });
        Mail::assertQueued(AccountCreatedMail::class, function (AccountCreatedMail $mail) {
            return data_get($mail->payload, 'user.email') === 'blake.booster@example.com'
                && data_get($mail->payload, 'user.role_label') === 'Booster';
        });
    }

    public function test_account_created_email_is_not_queued_when_account_creation_rolls_back(): void
    {
        Mail::fake();

        try {
            DB::transaction(function (): void {
                $user = User::factory()->create([
                    'role' => 'customer',
                    'account_status' => 'active',
                ]);

                app(AccountLifecycleEmailNotifier::class)->queueAccountCreated($user, 'self-service');

                throw new RuntimeException('Force rollback after account creation.');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Force rollback after account creation.', $exception->getMessage());
        }

        $this->assertSame(0, User::query()->where('role', 'customer')->count());
        Mail::assertNothingQueued();
    }

    public function test_account_created_notifier_deduplicates_the_same_account_event(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $notifier = app(AccountLifecycleEmailNotifier::class);

        $this->assertTrue($notifier->queueAccountCreated($user, 'self-service'));
        $this->assertFalse($notifier->queueAccountCreated($user, 'self-service'));

        Mail::assertQueued(AccountCreatedMail::class, 1);
        $this->assertDatabaseCount('transactional_email_dispatches', 1);
    }

    public function test_suspension_and_unsuspension_emails_only_send_on_real_status_transitions(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);
        $booster = User::factory()->create([
            'role' => 'booster',
            'account_status' => 'suspended',
        ]);

        $customerPayload = [
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'nickname' => $customer->nickname,
            'email' => $customer->email,
            'account_status' => 'active',
        ];

        $this->actingAs($admin)->patch(route('admin-customers.update', $customer), $customerPayload)
            ->assertRedirect(route('admin-customers.edit', $customer));

        Mail::assertNothingQueued();

        $customerPayload['account_status'] = 'suspended';

        $this->actingAs($admin)->patch(route('admin-customers.update', $customer), $customerPayload)
            ->assertRedirect(route('admin-customers.edit', $customer));

        $customerPayload['account_status'] = 'suspended';

        $this->actingAs($admin)->patch(route('admin-customers.update', $customer), $customerPayload)
            ->assertRedirect(route('admin-customers.edit', $customer));

        $this->actingAs($admin)->patch(route('admin-boosters.status', ['user' => $booster]))
            ->assertRedirect(route('admin-boosters.index'));

        Mail::assertQueued(AccountSuspendedMail::class, 1);
        Mail::assertQueued(AccountSuspendedMail::class, function (AccountSuspendedMail $mail) use ($customer) {
            return data_get($mail->payload, 'user.email') === $customer->email
                && data_get($mail->payload, 'account.changed_at_formatted') !== null;
        });
        Mail::assertQueued(AccountReactivatedMail::class, 1);
        Mail::assertQueued(AccountReactivatedMail::class, function (AccountReactivatedMail $mail) use ($booster) {
            return data_get($mail->payload, 'user.email') === $booster->email
                && data_get($mail->payload, 'account.changed_at_formatted') !== null;
        });
    }

    protected function makeAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
    }
}
