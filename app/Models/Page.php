<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = [
        'key',
        'meta_title',
        'meta_description',
        'canonical_url',
        'robots',
        'include_in_sitemap',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'include_in_sitemap' => 'boolean',
            'content' => 'array',
        ];
    }
}
