<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PlatformContentSeeder extends Seeder
{
    /**
     * Seed platform-managed marketing content.
     */
    public function run(): void
    {
        $this->call([
            PageSeeder::class,
            FaqSeeder::class,
            FeaturedBoosterSeeder::class,
            ReviewSeeder::class,
            BlogArticleSeeder::class,
        ]);
    }
}
