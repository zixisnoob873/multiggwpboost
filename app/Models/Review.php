<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $table = 'testimonials';

    protected $fillable = [
        'author_name',
        'game_id',
        'service_id',
        'service',
        'quote',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gameService(): BelongsTo
    {
        return $this->belongsTo(GameService::class, 'service_id');
    }
}
