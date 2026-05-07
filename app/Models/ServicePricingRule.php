<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ServicePricingRule extends Model
{
    use HasFactory;

    public const STATUS_PUBLISHED = 'published';

    public const SCOPE_BASE = 'base';

    public const SCOPE_ADDON = 'addon';

    public const PRICING_FIXED = 'fixed';

    public const PRICING_PERCENTAGE = 'percentage';

    public const PRICING_MULTIPLIER = 'multiplier';

    public const PRICING_DYNAMIC = 'dynamic';

    protected $fillable = [
        'game_id',
        'service_id',
        'addon_id',
        'slug',
        'name',
        'scope',
        'calculator_key',
        'pricing_type',
        'amount',
        'currency',
        'min_quantity',
        'max_quantity',
        'status',
        'sort_order',
        'conditions',
        'tiers',
        'metadata',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'min_quantity' => 'integer',
            'max_quantity' => 'integer',
            'sort_order' => 'integer',
            'conditions' => 'array',
            'tiers' => 'array',
            'metadata' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $rule): void {
            $rule->slug = Str::slug((string) ($rule->slug ?: $rule->name));
            $rule->currency = strtoupper((string) ($rule->currency ?: 'USD'));
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->where(function (Builder $builder): void {
                $builder->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $builder): void {
                $builder->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(GameService::class, 'service_id');
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(GameAddon::class, 'addon_id');
    }
}
