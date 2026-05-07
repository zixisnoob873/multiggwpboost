<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PricingSetting extends Model
{
    protected $fillable = [
        'key',
        'game_id',
        'config',
        'version',
        'checksum',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'version' => 'integer',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PricingSettingRevision::class);
    }
}
