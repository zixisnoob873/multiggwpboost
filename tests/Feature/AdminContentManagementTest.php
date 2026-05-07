<?php

namespace Tests\Feature;

use App\Models\AddonSetting;
use App\Models\FeaturedBooster;
use App\Models\User;
use App\Support\BoostingCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminContentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_featured_booster_page_uses_modal_create_and_edit_flows(): void
    {
        $admin = $this->makeAdmin();
        $booster = FeaturedBooster::query()->create([
            'name' => 'Radiant Ace',
            'region' => 'NA',
            'platform' => 'PC',
            'success_rate' => 98.5,
            'active_orders' => 4,
            'is_verified' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin-content.featured-boosters.index'))
            ->assertOk()
            ->assertSee('featuredBoosterCreateModal', false)
            ->assertSee('featuredBoosterEditModal'.$booster->id, false);

        $this->actingAs($admin)
            ->post(route('admin-featured-boosters.store'), [
                'modal_id' => 'featuredBoosterCreateModal',
                'featured_booster_context' => 'featured-booster-create',
                'featured_booster' => [
                    'name' => 'Clutch Captain',
                    'region' => 'EU',
                    'platform' => 'PC',
                    'success_rate' => 97.1,
                    'active_orders' => 6,
                    'is_verified' => '1',
                    'sort_order' => 2,
                ],
            ])
            ->assertRedirect(route('admin-content.featured-boosters.index'))
            ->assertSessionHas('status');

        $createdBooster = FeaturedBooster::query()->where('name', 'Clutch Captain')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin-featured-boosters.update', $createdBooster), [
                'modal_id' => 'featuredBoosterEditModal'.$createdBooster->id,
                'featured_booster_context' => 'featured-booster-'.$createdBooster->id,
                'featured_booster' => [
                    'name' => 'Clutch Captain Updated',
                    'region' => 'EU',
                    'platform' => 'Console',
                    'success_rate' => 99.2,
                    'active_orders' => 3,
                    'sort_order' => 5,
                ],
            ])
            ->assertRedirect(route('admin-content.featured-boosters.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('featured_boosters', [
            'id' => $createdBooster->id,
            'name' => 'Clutch Captain Updated',
            'platform' => 'Console',
            'sort_order' => 5,
        ]);
    }

    public function test_featured_booster_validation_preserves_the_modal_target(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->from(route('admin-content.featured-boosters.index'))
            ->post(route('admin-featured-boosters.store'), [
                'modal_id' => 'featuredBoosterCreateModal',
                'featured_booster_context' => 'featured-booster-create',
                'featured_booster' => [
                    'name' => '',
                    'region' => 'NA',
                    'platform' => 'PC',
                    'success_rate' => 101,
                    'active_orders' => -1,
                    'sort_order' => 1,
                ],
            ])
            ->assertRedirect(route('admin-content.featured-boosters.index'))
            ->assertSessionHasErrors(['name', 'success_rate', 'active_orders'])
            ->assertSessionHas('_old_input.modal_id', 'featuredBoosterCreateModal');
    }

    public function test_addon_tooltip_page_uses_modal_edit_flow(): void
    {
        $admin = $this->makeAdmin();
        $addonSlug = BoostingCatalog::addonSlugs()[0];
        $addonLabel = BoostingCatalog::addonLabelBySlug($addonSlug);

        $this->actingAs($admin)
            ->get(route('admin-content.addon-tooltips.index'))
            ->assertOk()
            ->assertSee($addonLabel)
            ->assertSee('addonTooltipModal'.$addonSlug, false);

        $this->actingAs($admin)
            ->patch(route('admin-addon-tooltips.update', ['addonSlug' => $addonSlug]), [
                'modal_id' => 'addonTooltipModal'.$addonSlug,
                'addon_tooltip_context' => 'addon-tooltip-'.$addonSlug,
                'addon_tooltip' => [
                    'description' => 'Short production-safe upsell copy.',
                ],
            ])
            ->assertRedirect(route('admin-content.addon-tooltips.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('addon_settings', [
            'slug' => $addonSlug,
            'label' => $addonLabel,
            'description' => 'Short production-safe upsell copy.',
        ]);
    }

    public function test_addon_tooltip_validation_preserves_the_modal_target(): void
    {
        $admin = $this->makeAdmin();
        $addonSlug = BoostingCatalog::addonSlugs()[0];

        AddonSetting::query()->updateOrCreate(
            ['slug' => $addonSlug],
            [
                'label' => BoostingCatalog::addonLabelBySlug($addonSlug),
                'description' => 'Existing description.',
                'sort_order' => 1,
            ]
        );

        $this->actingAs($admin)
            ->from(route('admin-content.addon-tooltips.index'))
            ->patch(route('admin-addon-tooltips.update', ['addonSlug' => $addonSlug]), [
                'modal_id' => 'addonTooltipModal'.$addonSlug,
                'addon_tooltip_context' => 'addon-tooltip-'.$addonSlug,
                'addon_tooltip' => [
                    'description' => str_repeat('A', 2001),
                ],
            ])
            ->assertRedirect(route('admin-content.addon-tooltips.index'))
            ->assertSessionHasErrors('description')
            ->assertSessionHas('_old_input.modal_id', 'addonTooltipModal'.$addonSlug);
    }

    protected function makeAdmin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'account_status' => 'active',
        ]);
    }
}
