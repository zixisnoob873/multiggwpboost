<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class GameAddon extends Model
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
        'label',
        'description',
        'icon',
        'status',
        'sort_order',
        'pricing_type',
        'pricing_value',
        'pricing_rule',
        'availability_rule',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'pricing_value' => 'decimal:4',
            'pricing_rule' => 'array',
            'availability_rule' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $addon): void {
            $addon->slug = Str::slug((string) ($addon->slug ?: $addon->label));
        });
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function serviceAssignments(): HasMany
    {
        return $this->hasMany(GameServiceAddon::class, 'game_addon_id')->orderBy('sort_order')->orderBy('id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(GameService::class, 'game_service_addons', 'game_addon_id', 'game_service_id')
            ->withPivot(['status', 'sort_order', 'availability_rule', 'metadata'])
            ->withTimestamps()
            ->orderBy('game_service_addons.sort_order')
            ->orderBy('game_services.id');
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(ServicePricingRule::class, 'addon_id')->orderBy('sort_order')->orderBy('id');
    }
}
