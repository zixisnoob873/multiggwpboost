<?php

namespace Database\Seeders;

use App\Models\FeaturedBooster;
use Illuminate\Database\Seeder;

class FeaturedBoosterSeeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            [
                'name' => 'RazeRunner',
                'region' => 'NA',
                'platform' => 'PC',
                'success_rate' => 98.5,
                'active_orders' => 3,
                'is_verified' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'VandalAce',
                'region' => 'EU',
                'platform' => 'PC',
                'success_rate' => 97.2,
                'active_orders' => 2,
                'is_verified' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'ClutchNova',
                'region' => 'APAC',
                'platform' => 'PC',
                'success_rate' => 96.8,
                'active_orders' => 4,
                'is_verified' => true,
                'sort_order' => 3,
            ],
        ];

        FeaturedBooster::query()->whereNotIn('sort_order', array_column($entries, 'sort_order'))->delete();

        foreach ($entries as $entry) {
            FeaturedBooster::query()->updateOrCreate(
                ['sort_order' => $entry['sort_order']],
                $entry
            );
        }
    }
}
