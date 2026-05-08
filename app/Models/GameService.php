<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;

class GameService extends Model
{
    use HasFactory;

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_PUBLISHED,
        self::STATUS_DRAFT,
        self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'game_id',
        'slug',
        'name',
        'kind',
        'description',
        'status',
        'sort_order',
        'config',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'config' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $service): void {
            $service->slug = Str::slug((string) ($service->slug ?: $service->name));
            $service->kind = Str::slug((string) ($service->kind ?: $service->name), '_');
        });
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function addonAssignments(): HasMany
    {
        return $this->hasMany(GameServiceAddon::class, 'game_service_id')->orderBy('sort_order')->orderBy('id');
    }

    public function addons(): BelongsToMany
    {
        return $this->belongsToMany(GameAddon::class, 'game_service_addons', 'game_service_id', 'game_addon_id')
            ->withPivot(['status', 'sort_order', 'availability_rule', 'metadata'])
            ->withTimestamps()
            ->orderBy('game_service_addons.sort_order')
            ->orderBy('game_addons.id');
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(ServicePricingRule::class, 'service_id')->orderBy('sort_order')->orderBy('id');
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(Faq::class, 'service_id')->orderBy('order')->orderBy('id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'service_id')->orderBy('sort_order')->orderBy('id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'service_id');
    }

    public function seoMetadata(): MorphOne
    {
        return $this->morphOne(SeoMetadata::class, 'seoable')->where('context', 'default');
    }
}
