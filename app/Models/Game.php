<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;

class Game extends Model
{
    use HasFactory;

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_DRAFT = 'draft';

    protected $fillable = [
        'game_category_id',
        'slug',
        'name',
        'short_name',
        'description',
        'status',
        'sort_order',
        'assets',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'assets' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $game): void {
            $game->slug = Str::slug((string) ($game->slug ?: $game->name));
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(GameCategory::class, 'game_category_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(GameService::class)->orderBy('sort_order')->orderBy('id');
    }

    public function ranks(): HasMany
    {
        return $this->hasMany(GameRank::class)->orderBy('sort_order')->orderBy('id');
    }

    public function addons(): HasMany
    {
        return $this->hasMany(GameAddon::class)->orderBy('sort_order')->orderBy('id');
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(ServicePricingRule::class)->orderBy('sort_order')->orderBy('id');
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(Faq::class)->orderBy('order')->orderBy('id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)->orderBy('sort_order')->orderBy('id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function seoMetadata(): MorphOne
    {
        return $this->morphOne(SeoMetadata::class, 'seoable')->where('context', 'default');
    }
}
