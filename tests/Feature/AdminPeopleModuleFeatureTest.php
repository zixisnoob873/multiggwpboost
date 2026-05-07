<?php

namespace Tests\Feature;

use App\Models\BoosterWalletAdjustment;
use App\Models\Order;
use App\Models\User;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminPeopleModuleFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_profile_page_renders_operational_details(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeUser('customer', [
            'name' => 'Jordan Queue',
            'nickname' => 'JordanQ',
            'email' => 'jordan@example.com',
        ]);
        $booster = $this->makeUser('booster');
        $order = $this->makeOrder($customer, $booster);

        $this->actingAs($admin)
            ->get(route('admin-customers.show', ['user' => $customer]))
            ->assertOk()
            ->assertSee('Customer Profile')
            ->assertSee('Jordan Queue')
            ->assertSee('jordan@example.com')
            ->assertSee($order->order_number);
    }

    public function test_booster_profile_page_renders_wallet_and_order_context(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster', [
            'name' => 'Ace Booster',
            'nickname' => 'AceBoost',
            'email' => 'ace@example.com',
        ]);
        $order = $this->makeOrder($customer, $booster);

        BoosterWalletAdjustment::query()->create([
            'booster_id' => $booster->id,
            'admin_id' => $admin->id,
            'type' => 'add',
            'amount_cents' => 2500,
            'reason' => 'Manual correction',
        ]);

        $this->actingAs($admin)
            ->get(route('admin-boosters.show', ['booster' => $booster->nickname]))
            ->assertOk()
            ->assertSee('Booster Profile')
            ->assertSee('Ace Booster')
            ->assertSee('ace@example.com')
            ->assertSee($order->order_number)
            ->assertSee('Wallet & Withdrawals');
    }

    public function test_super_admin_can_open_booster_edit_and_see_finance_actions(): void
    {
        $admin = $this->makeAdmin();
        $booster = $this->makeUser('booster', ['nickname' => 'OpsBooster']);

        $this->actingAs($admin)
            ->get(route('admin-boosters.edit', ['booster' => $booster->nickname]))
            ->assertOk()
            ->assertSee('Save Wallet Adjustment')
            ->assertSee('Open Finance Page')
            ->assertDontSee('Finance actions are restricted to admins with finance access.');
    }

    public function test_customer_cannot_access_people_module_pages_directly(): void
    {
        $customerActor = $this->makeUser('customer');
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');

        $this->actingAs($customerActor)
            ->get(route('admin-customers.index'))
            ->assertForbidden();

        $this->actingAs($customerActor)
            ->get(route('admin-boosters.show', ['booster' => $booster->nickname]))
            ->assertForbidden();
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

    protected function makeOrder(User $customer, ?User $booster = null, string $status = OrderStatus::IN_PROGRESS): Order
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
        ]);
    }
}
