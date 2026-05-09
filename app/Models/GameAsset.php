<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GameAsset extends Model
{
    use HasFactory;

    public const TYPE_CARD = 'card';
    public const TYPE_LOGO = 'logo';
    public const TYPE_BACKGROUND = 'background';
    public const TYPE_RANK_ICON = 'rank_icon';
    public const TYPE_CHARACTER_PORTRAIT = 'character_portrait';
    public const TYPE_ROLE_ICON = 'role_icon';

    protected $fillable = [
        'game_id',
        'asset_type',
        'slug',
        'label',
        'disk',
        'path',
        'source_url',
        'source_type',
        'source_name',
        'source_license_notes',
        'checksum',
        'width',
        'height',
        'alt_text',
        'source_updated_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'source_updated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $asset): void {
            $asset->slug = Str::slug((string) ($asset->slug ?: $asset->label ?: $asset->asset_type));
            $asset->disk = $asset->disk ?: 'public';
        });
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function url(): ?string
    {
        if (! $this->path) {
            return null;
        }

        return Storage::disk($this->disk ?: 'public')->url($this->path);
    }
}
