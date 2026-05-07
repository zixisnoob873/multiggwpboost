<?php

namespace App\Models;

use App\Support\BoostingCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoCodeAddon extends Model
{
    use HasFactory;

    protected $fillable = [
        'promo_code_id',
        'addon_slug',
        'discount_type',
        'discount_value',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
    ];

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function addonLabel(): string
    {
        return BoostingCatalog::addonLabelBySlug($this->addon_slug)
            ?? BoostingCatalog::normalizeAddon($this->addon_slug)
            ?? $this->addon_slug;
    }

    public function discountTypeLabel(): string
    {
        return match ($this->discount_type) {
            PromoCode::ADDON_DISCOUNT_TYPE_FREE => 'Free',
            PromoCode::ADDON_DISCOUNT_TYPE_FIXED => 'Fixed Price',
            default => 'Percent Price',
        };
    }

    public function displayDiscountValue(): string
    {
        return match ($this->discount_type) {
            PromoCode::ADDON_DISCOUNT_TYPE_FREE => '$0.00',
            PromoCode::ADDON_DISCOUNT_TYPE_FIXED => '$'.number_format((float) $this->discount_value, 2),
            default => rtrim(rtrim(number_format((float) $this->discount_value, 2), '0'), '.').'%',
        };
    }
}
