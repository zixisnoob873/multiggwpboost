<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Faq extends Model
{
    protected $fillable = [
        'question',
        'game_id',
        'service_id',
        'answer',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gameService(): BelongsTo
    {
        return $this->belongsTo(GameService::class, 'service_id');
    }
}
