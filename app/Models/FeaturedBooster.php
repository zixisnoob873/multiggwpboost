<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeaturedBooster extends Model
{
    protected $fillable = [
        'name',
        'region',
        'platform',
        'success_rate',
        'active_orders',
        'is_verified',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'success_rate' => 'decimal:2',
            'active_orders' => 'integer',
            'is_verified' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
