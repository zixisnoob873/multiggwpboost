<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PromoCode extends Model
{
    use HasFactory;

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED = 'fixed';

    public const TYPE_ADDON_PROMOCODE = 'addon';

    public const ADDON_DISCOUNT_TYPE_FREE = 'free';

    public const ADDON_DISCOUNT_TYPE_PERCENTAGE = 'percentage';

    public const ADDON_DISCOUNT_TYPE_FIXED = 'fixed';

    protected $fillable = [
        'code',
        'type',
        'value',
        'max_uses',
        'used_count',
        'start_at',
        'end_at',
        'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $promoCode) {
            $promoCode->code = Str::upper(trim((string) $promoCode->code));
        });
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function addonRules(): HasMany
    {
        return $this->hasMany(PromoCodeAddon::class)
            ->orderBy('addon_slug');
    }

    public function pendingCheckouts(): HasMany
    {
        return $this->hasMany(PendingCheckoutRecord::class);
    }

    public function isWithinActiveWindow(?\Illuminate\Support\Carbon $moment = null): bool
    {
        $moment ??= now();

        if ($this->start_at && $moment->lt($this->start_at)) {
            return false;
        }

        if ($this->end_at && $moment->gt($this->end_at)) {
            return false;
        }

        return true;
    }

    public function hasRemainingUses(): bool
    {
        return $this->max_uses === null || $this->used_count < $this->max_uses;
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_FIXED => 'Fixed',
            self::TYPE_ADDON_PROMOCODE => 'Addon Promo',
            default => 'Percentage',
        };
    }

    public function displayValue(): string
    {
        return match ($this->type) {
            self::TYPE_FIXED => '$'.number_format((float) $this->value, 2),
            self::TYPE_ADDON_PROMOCODE => sprintf(
                '%d addon %s',
                $this->addon_rules_count ?? $this->addonRules()->count(),
                (($this->addon_rules_count ?? $this->addonRules()->count()) === 1) ? 'rule' : 'rules'
            ),
            default => rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.').'%',
        };
    }

    public function usesAddonRules(): bool
    {
        return $this->type === self::TYPE_ADDON_PROMOCODE;
    }

    public function canBeDeleted(): bool
    {
        return (int) $this->used_count === 0
            && ! $this->orders()->exists()
            && ! $this->pendingCheckouts()->whereNull('completed_order_id')->exists();
    }

    public static function supportedTypes(): array
    {
        return [
            self::TYPE_PERCENTAGE,
            self::TYPE_FIXED,
            self::TYPE_ADDON_PROMOCODE,
        ];
    }

    public static function supportedAddonDiscountTypes(): array
    {
        return [
            self::ADDON_DISCOUNT_TYPE_FREE,
            self::ADDON_DISCOUNT_TYPE_PERCENTAGE,
            self::ADDON_DISCOUNT_TYPE_FIXED,
        ];
    }
}
