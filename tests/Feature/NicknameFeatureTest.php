<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NicknameFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_booster_edit_route_uses_nickname_and_resolves_case_insensitively(): void
    {
        $admin = $this->makeUser('admin', [
            'nickname' => 'AdminPrime',
        ]);
        $booster = $this->makeUser('booster', [
            'name' => 'Blake Carry',
            'first_name' => 'Blake',
            'last_name' => 'Carry',
            'nickname' => 'Feica',
            'nickname_normalized' => 'feica',
            'email' => 'feica@example.com',
        ]);

        $this->assertSame(url('/admin/boosters/Feica/edit'), route('admin-boosters.edit', ['booster' => $booster->nickname]));

        $this->actingAs($admin)
            ->get('/admin/boosters/feica/edit')
            ->assertOk()
            ->assertSee('Blake Carry')
            ->assertSee('Feica');
    }

    public function test_invalid_booster_nickname_route_returns_not_found(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)
            ->get('/admin/boosters/not-valid!/edit')
            ->assertNotFound();
    }

    public function test_admin_can_create_booster_with_valid_nickname_and_duplicate_case_insensitive_nicknames_are_rejected(): void
    {
        $admin = $this->makeUser('admin');
        $existingBooster = $this->makeUser('booster', [
            'nickname' => 'Feica',
            'nickname_normalized' => 'feica',
            'email' => 'existing-booster@example.com',
        ]);

        $this->actingAs($admin)
            ->from(route('admin-boosters.create'))
            ->post(route('admin-boosters.store'), [
                'first_name' => 'Prime',
                'last_name' => 'Carry',
                'nickname' => 'Feica',
                'email' => 'duplicate@example.com',
                'password' => 'ValidPass123!',
                'account_status' => 'active',
            ])
            ->assertRedirect(route('admin-boosters.create'))
            ->assertSessionHasErrors('nickname');

        $response = $this->actingAs($admin)
            ->post(route('admin-boosters.store'), [
                'first_name' => 'Prime',
                'last_name' => 'Carry',
                'nickname' => 'PrimeCarry7',
                'email' => 'prime-carry@example.com',
                'password' => 'ValidPass123!',
                'account_status' => 'active',
            ]);

        $response->assertRedirect(route('admin-boosters.index'));

        $this->assertDatabaseHas('users', [
            'role' => 'booster',
            'email' => 'prime-carry@example.com',
            'nickname' => 'PrimeCarry7',
            'nickname_normalized' => 'primecarry7',
        ]);

        $this->assertSame('Feica', $existingBooster->fresh()->nickname);
    }

    public function test_booster_nickname_validation_rejects_missing_invalid_and_overlong_values(): void
    {
        $admin = $this->makeUser('admin');

        $basePayload = [
            'first_name' => 'Prime',
            'last_name' => 'Carry',
            'email' => 'prime-carry@example.com',
            'password' => 'ValidPass123!',
            'account_status' => 'active',
        ];

        $this->actingAs($admin)
            ->from(route('admin-boosters.create'))
            ->post(route('admin-boosters.store'), array_merge($basePayload, [
                'nickname' => '',
            ]))
            ->assertRedirect(route('admin-boosters.create'))
            ->assertSessionHasErrors('nickname');

        $this->actingAs($admin)
            ->from(route('admin-boosters.create'))
            ->post(route('admin-boosters.store'), array_merge($basePayload, [
                'nickname' => 'Prime Carry',
            ]))
            ->assertRedirect(route('admin-boosters.create'))
            ->assertSessionHasErrors('nickname');

        $this->actingAs($admin)
            ->from(route('admin-boosters.create'))
            ->post(route('admin-boosters.store'), array_merge($basePayload, [
                'nickname' => str_repeat('A', 26),
            ]))
            ->assertRedirect(route('admin-boosters.create'))
            ->assertSessionHasErrors('nickname');
    }

    public function test_admin_booster_update_redirects_to_the_new_nickname_url(): void
    {
        $admin = $this->makeUser('admin');
        $booster = $this->makeUser('booster', [
            'nickname' => 'Feica',
            'nickname_normalized' => 'feica',
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('admin-boosters.update', ['booster' => $booster->nickname]), [
                'first_name' => $booster->first_name,
                'last_name' => $booster->last_name,
                'nickname' => 'PrimeCarry7',
                'email' => $booster->email,
                'account_status' => 'active',
            ]);

        $response->assertRedirect(route('admin-boosters.edit', ['booster' => 'PrimeCarry7']));

        $this->assertDatabaseHas('users', [
            'id' => $booster->id,
            'nickname' => 'PrimeCarry7',
            'nickname_normalized' => 'primecarry7',
        ]);
    }

    public function test_admin_customer_create_requires_unique_alphanumeric_nickname(): void
    {
        $admin = $this->makeUser('admin');
        $this->makeUser('customer', [
            'nickname' => 'CaseyP',
            'nickname_normalized' => 'caseyp',
            'email' => 'existing-customer@example.com',
        ]);

        $this->actingAs($admin)
            ->from(route('admin-customers.create'))
            ->post(route('admin-customers.store'), [
                'first_name' => 'Casey',
                'last_name' => 'Player',
                'nickname' => 'Casey P',
                'email' => 'casey.player@example.com',
                'password' => 'ValidPass123!',
                'account_status' => 'active',
            ])
            ->assertRedirect(route('admin-customers.create'))
            ->assertSessionHasErrors('nickname');

        $this->actingAs($admin)
            ->from(route('admin-customers.create'))
            ->post(route('admin-customers.store'), [
                'first_name' => 'Casey',
                'last_name' => 'Player',
                'nickname' => 'CASEYP',
                'email' => 'casey.duplicate@example.com',
                'password' => 'ValidPass123!',
                'account_status' => 'active',
            ])
            ->assertRedirect(route('admin-customers.create'))
            ->assertSessionHasErrors('nickname');
    }

    public function test_customer_registration_requires_valid_unique_nickname_and_persists_it(): void
    {
        $this->makeUser('customer', [
            'nickname' => 'TakenNick',
            'nickname_normalized' => 'takennick',
            'email' => 'taken@example.com',
        ]);

        $this->from(route('signup'))
            ->post(route('signup.submit'), [
                'first_name' => 'Demo',
                'last_name' => 'Player',
                'nickname' => '',
                'email' => 'demo@example.com',
                'password' => 'ValidPass123!',
                'password_confirmation' => 'ValidPass123!',
                'accepted_terms' => '1',
            ])
            ->assertRedirect(route('signup'))
            ->assertSessionHasErrors('nickname');

        $this->from(route('signup'))
            ->post(route('signup.submit'), [
                'first_name' => 'Demo',
                'last_name' => 'Player',
                'nickname' => 'Demo Player',
                'email' => 'demo-two@example.com',
                'password' => 'ValidPass123!',
                'password_confirmation' => 'ValidPass123!',
                'accepted_terms' => '1',
            ])
            ->assertRedirect(route('signup'))
            ->assertSessionHasErrors('nickname');

        $this->from(route('signup'))
            ->post(route('signup.submit'), [
                'first_name' => 'Demo',
                'last_name' => 'Player',
                'nickname' => 'TAKENNICK',
                'email' => 'demo-three@example.com',
                'password' => 'ValidPass123!',
                'password_confirmation' => 'ValidPass123!',
                'accepted_terms' => '1',
            ])
            ->assertRedirect(route('signup'))
            ->assertSessionHasErrors('nickname');

        $response = $this->post(route('signup.submit'), [
            'first_name' => 'Demo',
            'last_name' => 'Player',
            'nickname' => 'DemoPlayer7',
            'email' => 'demo-valid@example.com',
            'password' => 'ValidPass123!',
            'password_confirmation' => 'ValidPass123!',
            'accepted_terms' => '1',
        ]);

        $response->assertRedirect(route('customer-dashboard'));

        $this->assertDatabaseHas('users', [
            'email' => 'demo-valid@example.com',
            'nickname' => 'DemoPlayer7',
            'nickname_normalized' => 'demoplayer7',
            'role' => 'customer',
        ]);
    }

    public function test_nickname_fields_render_frontend_validation_attributes(): void
    {
        $admin = $this->makeUser('admin');

        $this->get(route('signup'))
            ->assertOk()
            ->assertSee('name="nickname"', false)
            ->assertSee('maxlength="25"', false)
            ->assertSee('pattern="[A-Za-z0-9]+"', false);

        $this->actingAs($admin)
            ->get(route('admin-boosters.create'))
            ->assertOk()
            ->assertSee('name="nickname"', false)
            ->assertSee('maxlength="25"', false)
            ->assertSee('pattern="[A-Za-z0-9]+"', false)
            ->assertSee('data-nickname-input', false);

        $this->actingAs($admin)
            ->get(route('admin-customers.create'))
            ->assertOk()
            ->assertSee('name="nickname"', false)
            ->assertSee('maxlength="25"', false)
            ->assertSee('pattern="[A-Za-z0-9]+"', false);
    }

    public function test_customer_and_booster_pages_show_nicknames_instead_of_real_names(): void
    {
        $customer = $this->makeUser('customer', [
            'name' => 'Casey Player',
            'first_name' => 'Casey',
            'last_name' => 'Player',
            'nickname' => 'CaseyP',
            'nickname_normalized' => 'caseyp',
            'email' => 'casey@example.com',
        ]);
        $booster = $this->makeUser('booster', [
            'name' => 'Blake Carry',
            'first_name' => 'Blake',
            'last_name' => 'Carry',
            'nickname' => 'Feica',
            'nickname_normalized' => 'feica',
            'email' => 'feica@example.com',
        ]);
        $order = $this->makeOrder($customer, $booster);

        $this->actingAs($customer)
            ->get(route('customer-dashboard'))
            ->assertOk()
            ->assertSee('CaseyP')
            ->assertDontSee('Casey Player');

        $this->actingAs($customer)
            ->get(route('user-chats.show', ['order' => $order]))
            ->assertOk()
            ->assertSee('Feica')
            ->assertDontSee('Blake Carry');

        $this->actingAs($booster)
            ->get(route('booster-dashboard'))
            ->assertOk()
            ->assertSee('Feica')
            ->assertDontSee('Blake Carry');

        $this->actingAs($booster)
            ->get(route('booster-chats.show', ['order' => $order]))
            ->assertOk()
            ->assertSee('CaseyP')
            ->assertDontSee('Casey Player');
    }

    protected function makeUser(string $role, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'account_status' => 'active',
        ], $overrides));
    }

    protected function makeOrder(User $customer, ?User $booster = null): Order
    {
        return Order::query()->create([
            'user_id' => $customer->id,
            'booster_id' => $booster?->id,
            'order_number' => (string) Str::orderedUuid(),
            'product' => 'Rank Boosting',
            'status' => OrderStatus::IN_PROGRESS,
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
}
