<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AddonSetting extends Model
{
    protected $fillable = [
        'slug',
        'label',
        'description',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
