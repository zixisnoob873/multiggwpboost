<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Game>
 */
class GameFactory extends Factory
{
    protected $model = Game::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'game_category_id' => GameCategory::factory(),
            'slug' => Str::slug($name),
            'name' => Str::title($name),
            'short_name' => Str::upper(Str::substr(Str::slug($name, ''), 0, 8)),
            'description' => fake()->sentence(),
            'status' => Game::STATUS_PUBLISHED,
            'sort_order' => fake()->numberBetween(1, 100),
            'assets' => [],
            'metadata' => [],
        ];
    }
}
