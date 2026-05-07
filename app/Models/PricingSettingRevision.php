<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingSettingRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'pricing_setting_id',
        'key',
        'action',
        'version',
        'checksum',
        'config',
        'actor_id',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'config' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function pricingSetting(): BelongsTo
    {
        return $this->belongsTo(PricingSetting::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
