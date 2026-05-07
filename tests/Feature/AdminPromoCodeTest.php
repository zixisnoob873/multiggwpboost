<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\PromoCodeService;
use App\Support\BoostingCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminPromoCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_promo_code_with_unlimited_validity(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);

        $response = $this->actingAs($admin)->post(route('admin-promo-codes.store'), [
            'code' => 'FOREVER20',
            'type' => 'percentage',
            'value' => 20,
            'max_uses' => null,
            'unlimited_validity' => '1',
            'is_active' => '1',
        ]);

        $response
            ->assertRedirect(route('admin-promo-codes.index'))
            ->assertSessionHas('status');

        $promoCode = PromoCode::query()->where('code', 'FOREVER20')->firstOrFail();

        $this->assertNull($promoCode->start_at);
        $this->assertNull($promoCode->end_at);
        $this->assertTrue($promoCode->is_active);
    }

    public function test_admin_can_create_addon_promo_codes_with_managed_addon_rules(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        [$firstAddonSlug, $secondAddonSlug] = array_values(array_slice(BoostingCatalog::addonSlugs(), 0, 2));

        $response = $this->actingAs($admin)->post(route('admin-promo-codes.store'), [
            'code' => 'ADDONPROMO',
            'type' => PromoCode::TYPE_ADDON_PROMOCODE,
            'max_uses' => 10,
            'unlimited_validity' => '1',
            'is_active' => '1',
            'addon_rules' => [
                [
                    'selected' => '1',
                    'addon_slug' => $firstAddonSlug,
                    'discount_type' => PromoCode::ADDON_DISCOUNT_TYPE_FREE,
                    'discount_value' => '0',
                ],
                [
                    'selected' => '1',
                    'addon_slug' => $secondAddonSlug,
                    'discount_type' => PromoCode::ADDON_DISCOUNT_TYPE_PERCENTAGE,
                    'discount_value' => '25',
                ],
            ],
        ]);

        $response
            ->assertRedirect(route('admin-promo-codes.index'))
            ->assertSessionHas('status');

        $promoCode = PromoCode::query()->where('code', 'ADDONPROMO')->firstOrFail();

        $this->assertSame(PromoCode::TYPE_ADDON_PROMOCODE, $promoCode->type);
        $this->assertSame('0.00', (string) $promoCode->value);
        $this->assertDatabaseHas('promo_code_addons', [
            'promo_code_id' => $promoCode->id,
            'addon_slug' => $firstAddonSlug,
            'discount_type' => PromoCode::ADDON_DISCOUNT_TYPE_FREE,
        ]);
        $this->assertDatabaseHas('promo_code_addons', [
            'promo_code_id' => $promoCode->id,
            'addon_slug' => $secondAddonSlug,
            'discount_type' => PromoCode::ADDON_DISCOUNT_TYPE_PERCENTAGE,
        ]);
    }

    public function test_non_addon_promo_codes_reject_addon_rule_payloads(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $addonSlug = BoostingCatalog::addonSlugs()[0];

        $this->actingAs($admin)
            ->from(route('admin-promo-codes.index'))
            ->post(route('admin-promo-codes.store'), [
                'code' => 'BADRULES',
                'type' => PromoCode::TYPE_PERCENTAGE,
                'value' => 15,
                'unlimited_validity' => '1',
                'is_active' => '1',
                'addon_rules' => [
                    [
                        'selected' => '1',
                        'addon_slug' => $addonSlug,
                        'discount_type' => PromoCode::ADDON_DISCOUNT_TYPE_FREE,
                        'discount_value' => '0',
                    ],
                ],
            ])
            ->assertRedirect(route('admin-promo-codes.index'))
            ->assertSessionHasErrors('addon_rules');

        $this->assertDatabaseMissing('promo_codes', [
            'code' => 'BADRULES',
        ]);
    }

    public function test_admin_can_search_promo_codes_and_index_uses_details_and_edit_actions_only(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $matchingPromoCode = PromoCode::factory()->create([
            'code' => 'BOOST10',
            'is_active' => true,
        ]);
        PromoCode::factory()->create([
            'code' => 'SAVE15',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin-promo-codes.index', ['search' => 'BOOST']));

        $response
            ->assertOk()
            ->assertSee('BOOST10')
            ->assertDontSee('SAVE15')
            ->assertSee(route('admin-promo-codes.details', $matchingPromoCode), false)
            ->assertSee(route('admin-promo-codes.edit', $matchingPromoCode), false)
            ->assertDontSee(route('admin-promo-codes.deactivate', $matchingPromoCode), false)
            ->assertDontSee('action="'.route('admin-promo-codes.destroy', $matchingPromoCode).'"', false);

        $this->assertTrue(Route::has('admin-promo-codes.destroy'));
    }

    public function test_mutating_promo_code_actions_live_on_the_edit_screen_only(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $promoCode = PromoCode::factory()->create([
            'code' => 'EDITONLY',
            'is_active' => true,
            'used_count' => 0,
        ]);

        $this->actingAs($admin)
            ->get(route('admin-promo-codes.edit', $promoCode))
            ->assertOk()
            ->assertSee(route('admin-promo-codes.deactivate', $promoCode), false)
            ->assertSee('action="'.route('admin-promo-codes.destroy', $promoCode).'"', false);

        $this->actingAs($admin)
            ->get(route('admin-promo-codes.details', $promoCode))
            ->assertOk()
            ->assertSee(route('admin-promo-codes.edit', $promoCode), false)
            ->assertDontSee(route('admin-promo-codes.deactivate', $promoCode), false)
            ->assertDontSee('action="'.route('admin-promo-codes.destroy', $promoCode).'"', false);
    }

    public function test_admin_can_view_promo_code_details_with_real_usage_statistics_and_orders(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $promoCode = PromoCode::factory()->create([
            'code' => 'DETAILS20',
            'is_active' => true,
        ]);
        $otherPromoCode = PromoCode::factory()->create([
            'code' => 'OTHER10',
        ]);
        $firstCustomer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
            'name' => 'Casey Player',
            'email' => 'casey@example.com',
        ]);
        $secondCustomer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
            'name' => 'Jordan Queue',
            'email' => 'jordan@example.com',
        ]);

        $firstOrder = $this->makePromoOrder($firstCustomer, $promoCode, [
            'product' => 'Rank Boosting',
            'price_cents' => 9000,
            'discount_amount' => 5.00,
        ]);
        $secondOrder = $this->makePromoOrder($secondCustomer, $promoCode, [
            'product' => 'Ranked Wins',
            'price_cents' => 10000,
            'discount_amount' => 10.00,
            'details' => [
                'order' => [
                    'orderType' => 'Ranked Wins',
                    'currentDivision' => 'Diamond I',
                    'numberOfWins' => 4,
                ],
            ],
        ]);
        $ignoredOrder = $this->makePromoOrder($firstCustomer, $otherPromoCode, [
            'price_cents' => 5000,
            'discount_amount' => 2.50,
        ]);

        $response = $this->actingAs($admin)->get(route('admin-promo-codes.details', $promoCode));

        $response
            ->assertOk()
            ->assertSee('DETAILS20')
            ->assertSee('2 orders')
            ->assertSee('$15.00')
            ->assertSee('$190.00')
            ->assertSee('$205.00')
            ->assertSee($firstOrder->order_number)
            ->assertSee($secondOrder->order_number)
            ->assertSee('Casey Player')
            ->assertSee('Jordan Queue')
            ->assertSee('Booster Payout Basis')
            ->assertDontSee($ignoredOrder->order_number);
    }

    public function test_admin_can_deactivate_promo_codes_and_inactive_codes_are_rejected(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $promoCode = PromoCode::factory()->create([
            'code' => 'PAUSEME',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin-promo-codes.deactivate', $promoCode));

        $response
            ->assertRedirect(route('admin-promo-codes.edit', $promoCode))
            ->assertSessionHas('status', 'Promo code PAUSEME deactivated.');

        $this->assertFalse($promoCode->fresh()->is_active);

        $validationResult = app(PromoCodeService::class)->validateCode('PAUSEME', 50);

        $this->assertFalse($validationResult->valid);
        $this->assertSame('This promo code is disabled.', $validationResult->firstError());
    }

    public function test_promo_code_details_page_returns_not_found_for_invalid_id(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get('/admin/promo-codes/999/details')
            ->assertNotFound();
    }

    protected function makePromoOrder(User $customer, PromoCode $promoCode, array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'user_id' => $customer->id,
            'promo_code_id' => $promoCode->id,
            'order_number' => (string) Str::orderedUuid(),
            'product' => 'Rank Boosting',
            'status' => 'Completed',
            'payment_status' => 'paid',
            'price_cents' => 9500,
            'discount_amount' => 4.50,
            'booster_payout_rate' => 0.6,
            'booster_payout_cents' => 5700,
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
                    'firstName' => $customer->first_name,
                    'lastName' => $customer->last_name,
                    'email' => $customer->email,
                ],
            ],
        ], $overrides));
    }
}
