<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GameRank extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'slug',
        'label',
        'division',
        'sort_order',
        'icon_url',
        'icon_path',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $rank): void {
            $rank->slug = Str::slug((string) ($rank->slug ?: $rank->label));
        });
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
