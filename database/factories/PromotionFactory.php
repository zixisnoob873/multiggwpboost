<?php

namespace Database\Factories;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(3),
            'description' => fake()->sentence(12),
            'image_path' => 'promotion_pics/'.fake()->uuid().'.jpg',
            'button_text' => 'Learn More',
            'button_link' => '/#servicesTab',
            'is_active' => true,
            'show_on_homepage' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function hiddenFromHomepage(): static
    {
        return $this->state(fn () => [
            'show_on_homepage' => false,
        ]);
    }
}
