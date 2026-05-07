<?php

namespace Database\Factories;

use App\Models\PromoCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PromoCode>
 */
class PromoCodeFactory extends Factory
{
    protected $model = PromoCode::class;

    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->bothify('BOOST-###??')),
            'type' => PromoCode::TYPE_PERCENTAGE,
            'value' => 10,
            'max_uses' => null,
            'used_count' => 0,
            'start_at' => now()->subDay(),
            'end_at' => now()->addWeek(),
            'is_active' => true,
        ];
    }

    public function fixed(float $value = 5): static
    {
        return $this->state(fn () => [
            'type' => PromoCode::TYPE_FIXED,
            'value' => $value,
        ]);
    }

    public function addonPromo(): static
    {
        return $this->state(fn () => [
            'type' => PromoCode::TYPE_ADDON_PROMOCODE,
            'value' => 0,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'start_at' => now()->subWeek(),
            'end_at' => now()->subMinute(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function maxedOut(int $maxUses = 1): static
    {
        return $this->state(fn () => [
            'max_uses' => $maxUses,
            'used_count' => $maxUses,
        ]);
    }
}
