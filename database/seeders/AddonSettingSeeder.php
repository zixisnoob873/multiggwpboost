<?php

namespace Database\Seeders;

use App\Models\AddonSetting;
use App\Support\BoostingCatalog;
use Illuminate\Database\Seeder;

class AddonSettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (BoostingCatalog::addonDefinitions() as $addon) {
            AddonSetting::updateOrCreate(
                ['slug' => $addon['slug']],
                [
                    'label' => $addon['label'],
                    'description' => $addon['description'],
                    'sort_order' => $addon['sort_order'],
                ]
            );
        }
    }
}
