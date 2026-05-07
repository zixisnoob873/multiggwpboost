<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\ServicePricingRule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServicePricingRule>
 */
class ServicePricingRuleFactory extends Factory
{
    protected $model = ServicePricingRule::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'game_id' => Game::factory(),
            'service_id' => null,
            'addon_id' => null,
            'slug' => Str::slug($name),
            'name' => Str::title($name),
            'scope' => ServicePricingRule::SCOPE_BASE,
            'calculator_key' => 'flat_service',
            'pricing_type' => ServicePricingRule::PRICING_FIXED,
            'amount' => fake()->randomFloat(2, 5, 50),
            'currency' => 'USD',
            'min_quantity' => null,
            'max_quantity' => null,
            'status' => ServicePricingRule::STATUS_PUBLISHED,
            'sort_order' => fake()->numberBetween(1, 100),
            'conditions' => [],
            'tiers' => [],
            'metadata' => [],
        ];
    }
}
