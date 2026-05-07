<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GameService>
 */
class GameServiceFactory extends Factory
{
    protected $model = GameService::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Rank Boosting',
            'Placement Matches',
            'Coaching',
            'Power Leveling',
            'Weapon Leveling',
        ]).'-'.fake()->unique()->numberBetween(1000, 9999);

        return [
            'game_id' => Game::factory(),
            'slug' => Str::slug($name),
            'name' => $name,
            'kind' => Str::slug($name, '_'),
            'description' => fake()->sentence(),
            'status' => Game::STATUS_PUBLISHED,
            'sort_order' => fake()->numberBetween(1, 100),
            'config' => [],
            'metadata' => [],
        ];
    }
}
