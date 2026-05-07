<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameServiceAddon extends Model
{
    protected $fillable = [
        'game_service_id',
        'game_addon_id',
        'status',
        'sort_order',
        'availability_rule',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'availability_rule' => 'array',
            'metadata' => 'array',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(GameService::class, 'game_service_id');
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(GameAddon::class, 'game_addon_id');
    }
}
