<?php

namespace App\Actions\Admin;

use App\Models\FeaturedBooster;

class StoreFeaturedBoosterAction
{
    public function execute(array $data): FeaturedBooster
    {
        return FeaturedBooster::create($this->payload($data));
    }

    private function payload(array $data): array
    {
        return $data + [
            'is_verified' => (bool) ($data['is_verified'] ?? false),
        ];
    }
}
