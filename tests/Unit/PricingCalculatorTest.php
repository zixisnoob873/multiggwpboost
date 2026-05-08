<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GameAddon;
use App\Models\GameRank;
use App\Models\GameService;
use App\Models\ServicePricingRule;
use App\Services\Pricing\PricingCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixed_base_price_returns_expected_total(): void
    {
        [$game, $service] = $this->catalogService(baseAmount: 42.50);

        $result = app(PricingCalculator::class)->calculatePayloadOrFail([
            'gameSlug' => $game->slug,
            'serviceSlug' => $service->slug,
            'serviceType' => $service->name,
        ]);

        $this->assertSame(42.50, $result['basePrice']);
        $this->assertSame(4250, $result['finalPriceCents']);
        $this->assertSame(42.50, $result['pricing']['total']);
    }

    public function test_rank_to_rank_step_table_sums_each_rank_step(): void
    {
        [$game, $service] = $this->catalogService(kind: 'rank_boost', baseAmount: 9.00, calculatorKey: 'rank_to_rank', tiers: [
            'steps' => [
                'Bronze->Silver' => 10,
                'Silver->Gold' => 15,
            ],
        ]);
        $this->ranks($game, ['Bronze', 'Silver', 'Gold']);

        $result = app(PricingCalculator::class)->calculatePayloadOrFail([
            'gameSlug' => $game->slug,
            'serviceSlug' => $service->slug,
            'currentDivision' => 'Bronze',
            'desiredDivision' => 'Gold',
        ]);

        $this->assertSame(25.00, $result['basePrice']);
        $this->assertSame(2500, $result['finalPriceCents']);
        $this->assertCount(2, $result['rankPath']);
        $this->assertSame(['from' => 'Bronze', 'to' => 'Silver', 'amount' => 10.00], $result['rankPath'][0]);
    }

    public function test_flat_addon_adds_fixed_amount(): void
    {
        [$game, $service] = $this->catalogService(baseAmount: 100);
        $this->addon($game, $service, 'VPN Protection', ServicePricingRule::PRICING_FIXED, 12.50);

        $result = app(PricingCalculator::class)->calculatePayloadOrFail([
            'gameSlug' => $game->slug,
            'serviceSlug' => $service->slug,
            'selectedAddons' => ['VPN Protection'],
        ]);

        $this->assertSame(112.50, $result['pricing']['total']);
        $this->assertSame(12.50, $result['addonBreakdown'][0]['amount']);
    }

    public function test_percentage_addon_applies_to_pre_addon_service_subtotal(): void
    {
        [$game, $service] = $this->catalogService(baseAmount: 100);
        $this->addon($game, $service, 'Coaching Review', ServicePricingRule::PRICING_PERCENTAGE, 20);

        $result = app(PricingCalculator::class)->calculatePayloadOrFail([
            'gameSlug' => $game->slug,
            'serviceSlug' => $service->slug,
            'selectedAddons' => ['Coaching Review'],
        ]);

        $this->assertSame(120.00, $result['pricing']['total']);
        $this->assertSame(20.00, $result['addonBreakdown'][0]['amount']);
    }

    public function test_multiplier_addon_applies_after_additive_addons(): void
    {
        [$game, $service] = $this->catalogService(baseAmount: 100);
        $this->addon($game, $service, 'VPN Protection', ServicePricingRule::PRICING_FIXED, 10);
        $this->addon($game, $service, 'Priority Order', ServicePricingRule::PRICING_MULTIPLIER, 1.5);

        $result = app(PricingCalculator::class)->calculatePayloadOrFail([
            'gameSlug' => $game->slug,
            'serviceSlug' => $service->slug,
            'selectedAddons' => ['VPN Protection', 'Priority Order'],
        ]);

        $this->assertSame(165.00, $result['pricing']['total']);
        $this->assertSame(55.00, $result['addonBreakdown'][1]['amount']);
    }

    public function test_express_and_duo_queue_multipliers_use_configured_addons(): void
    {
        [$game, $service] = $this->catalogService(baseAmount: 100);
        $this->addon($game, $service, 'VPN Protection', ServicePricingRule::PRICING_FIXED, 10);
        $this->addon($game, $service, 'Duo Queue', ServicePricingRule::PRICING_MULTIPLIER, 1.2);
        $this->addon($game, $service, 'Express Delivery', ServicePricingRule::PRICING_MULTIPLIER, 1.25);

        $result = app(PricingCalculator::class)->calculatePayloadOrFail([
            'gameSlug' => $game->slug,
            'serviceSlug' => $service->slug,
            'selectedAddons' => ['VPN Protection'],
            'duoQueue' => true,
            'expressDelivery' => true,
        ]);

        $this->assertSame(165.00, $result['pricing']['total']);
        $this->assertSame(['VPN Protection', 'Duo Queue', 'Express Delivery'], $result['addons']);
    }

    public function test_invalid_addon_service_combination_is_rejected(): void
    {
        [$game, $service] = $this->catalogService(baseAmount: 100);
        GameAddon::factory()->create([
            'game_id' => $game->id,
            'slug' => 'unattached-addon',
            'label' => 'Unattached Addon',
            'status' => Game::STATUS_PUBLISHED,
        ]);

        $result = app(PricingCalculator::class)->calculate([
            'gameSlug' => $game->slug,
            'serviceSlug' => $service->slug,
            'selectedAddons' => ['Unattached Addon'],
        ]);

        $this->assertTrue($result->hasValidationErrors());
        $this->assertSame(['Unattached Addon is not available for this service.'], $result->validationErrors['selectedAddons']);
    }

    protected function catalogService(
        string $kind = 'coaching',
        float $baseAmount = 100,
        string $calculatorKey = 'flat_service',
        array $tiers = [],
    ): array {
        $game = Game::factory()->create([
            'slug' => 'test-arena-'.strtolower(fake()->unique()->bothify('####')),
            'name' => 'Test Arena',
            'status' => Game::STATUS_PUBLISHED,
        ]);
        $service = GameService::factory()->create([
            'game_id' => $game->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'kind' => $kind,
            'status' => Game::STATUS_PUBLISHED,
        ]);

        ServicePricingRule::factory()->create([
            'game_id' => $game->id,
            'service_id' => $service->id,
            'addon_id' => null,
            'slug' => 'base-'.$service->id,
            'name' => 'Base Price',
            'scope' => ServicePricingRule::SCOPE_BASE,
            'calculator_key' => $calculatorKey,
            'pricing_type' => ServicePricingRule::PRICING_FIXED,
            'amount' => $baseAmount,
            'tiers' => $tiers,
            'status' => ServicePricingRule::STATUS_PUBLISHED,
        ]);

        return [$game, $service];
    }

    protected function ranks(Game $game, array $labels): void
    {
        foreach ($labels as $index => $label) {
            GameRank::factory()->create([
                'game_id' => $game->id,
                'slug' => str($label)->slug(),
                'label' => $label,
                'sort_order' => $index + 1,
            ]);
        }
    }

    protected function addon(Game $game, GameService $service, string $label, string $type, float $amount): GameAddon
    {
        $addon = GameAddon::factory()->create([
            'game_id' => $game->id,
            'slug' => str($label)->slug(),
            'label' => $label,
            'pricing_type' => $type,
            'pricing_value' => $amount,
            'status' => Game::STATUS_PUBLISHED,
        ]);

        $service->addons()->attach($addon->id, [
            'status' => Game::STATUS_PUBLISHED,
            'sort_order' => 1,
        ]);

        ServicePricingRule::factory()->create([
            'game_id' => $game->id,
            'service_id' => $service->id,
            'addon_id' => $addon->id,
            'slug' => 'addon-'.$service->id.'-'.$addon->id,
            'name' => $label,
            'scope' => ServicePricingRule::SCOPE_ADDON,
            'calculator_key' => 'addon',
            'pricing_type' => $type,
            'amount' => $amount,
            'status' => ServicePricingRule::STATUS_PUBLISHED,
        ]);

        return $addon;
    }
}
