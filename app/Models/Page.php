<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Page extends Model
{
    protected $fillable = [
        'key',
        'game_id',
        'service_id',
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

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gameService(): BelongsTo
    {
        return $this->belongsTo(GameService::class, 'service_id');
    }

    public function seoMetadata(): MorphOne
    {
        return $this->morphOne(SeoMetadata::class, 'seoable')->where('context', 'default');
    }
}
