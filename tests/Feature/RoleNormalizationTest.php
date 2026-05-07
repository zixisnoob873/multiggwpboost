<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RoleNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_admin_and_manager_roles_are_normalized_to_super_admin(): void
    {
        $legacyAdmin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $legacyManager = User::factory()->create([
            'role' => 'manager',
            'account_status' => 'active',
        ]);

        $this->assertSame(User::ROLE_SUPER_ADMIN, $legacyAdmin->fresh()->role);
        $this->assertSame(User::ROLE_SUPER_ADMIN, $legacyManager->fresh()->role);
        $this->assertFalse(Schema::hasColumn('users', 'admin_role'));
    }

    public function test_admin_sub_role_strings_are_not_admin_roles(): void
    {
        foreach (['ops_admin', 'content_admin', 'finance_admin'] as $role) {
            $user = User::factory()->create([
                'role' => $role,
                'account_status' => 'active',
            ]);

            $this->assertSame($role, $user->fresh()->role);

            $this->actingAs($user)
                ->get(route('admin-dashboard'))
                ->assertForbidden();
        }
    }

    public function test_super_admin_can_access_admin_dashboard_while_customer_and_booster_cannot(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'account_status' => 'active',
        ]);
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'account_status' => 'active',
        ]);
        $booster = User::factory()->create([
            'role' => User::ROLE_BOOSTER,
            'account_status' => 'active',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('admin-dashboard'))
            ->assertOk();

        $this->actingAs($customer)
            ->get(route('admin-dashboard'))
            ->assertForbidden();

        $this->actingAs($booster)
            ->get(route('admin-dashboard'))
            ->assertForbidden();
    }
}
