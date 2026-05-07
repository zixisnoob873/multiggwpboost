<?php

namespace Database\Seeders;

use App\Support\Pricing\ValorantPricingConfigRepository;
use Illuminate\Database\Seeder;

class PricingSettingSeeder extends Seeder
{
    public function run(): void
    {
        app(ValorantPricingConfigRepository::class)->seedDefaults();
    }
}
