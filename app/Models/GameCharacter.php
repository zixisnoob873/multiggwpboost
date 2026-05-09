<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GameCharacter extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'slug',
        'name',
        'role',
        'portrait_asset_id',
        'source_id',
        'source_type',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $character): void {
            $character->slug = Str::slug((string) ($character->slug ?: $character->name));
        });
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function portrait(): BelongsTo
    {
        return $this->belongsTo(GameAsset::class, 'portrait_asset_id');
    }
}
