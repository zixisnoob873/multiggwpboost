<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameAddon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GameAddon>
 */
class GameAddonFactory extends Factory
{
    protected $model = GameAddon::class;

    public function definition(): array
    {
        $label = fake()->unique()->randomElement([
            'Duo Queue',
            'Offline Mode',
            'VPN Protection',
            'Streamed Games',
            'Priority Order',
            'Express Delivery',
        ]).'-'.fake()->unique()->numberBetween(1000, 9999);

        return [
            'game_id' => Game::factory(),
            'slug' => Str::slug($label),
            'label' => $label,
            'description' => fake()->sentence(),
            'icon' => null,
            'status' => Game::STATUS_PUBLISHED,
            'sort_order' => fake()->numberBetween(1, 100),
            'pricing_type' => 'fixed',
            'pricing_value' => 5.00,
            'pricing_rule' => ['type' => 'fixed', 'value' => 5.00],
            'availability_rule' => [],
            'metadata' => [],
        ];
    }
}
