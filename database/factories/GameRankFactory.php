<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameRank;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GameRank>
 */
class GameRankFactory extends Factory
{
    protected $model = GameRank::class;

    public function definition(): array
    {
        $label = fake()->unique()->randomElement(['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond'])
            .' '.fake()->randomElement(['I', 'II', 'III', 'IV']);

        return [
            'game_id' => Game::factory(),
            'slug' => Str::slug($label),
            'label' => $label,
            'division' => Str::after($label, ' '),
            'sort_order' => fake()->numberBetween(1, 100),
            'icon_url' => null,
            'icon_path' => null,
            'metadata' => [],
        ];
    }
}
