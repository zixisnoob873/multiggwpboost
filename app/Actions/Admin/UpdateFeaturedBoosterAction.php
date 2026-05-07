<?php

namespace App\Actions\Admin;

use App\Models\FeaturedBooster;

class UpdateFeaturedBoosterAction
{
    public function execute(FeaturedBooster $featuredBooster, array $data): FeaturedBooster
    {
        $featuredBooster->update($this->payload($data));

        return $featuredBooster;
    }

    private function payload(array $data): array
    {
        return $data + [
            'is_verified' => (bool) ($data['is_verified'] ?? false),
        ];
    }
}
