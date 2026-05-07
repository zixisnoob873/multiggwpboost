<?php

namespace Database\Factories;

use App\Models\GameCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GameCategory>
 */
class GameCategoryFactory extends Factory
{
    protected $model = GameCategory::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name),
            'name' => Str::title($name),
            'description' => fake()->sentence(),
            'status' => GameCategory::STATUS_PUBLISHED,
            'sort_order' => fake()->numberBetween(1, 100),
            'metadata' => [],
        ];
    }
}
