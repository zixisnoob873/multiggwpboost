<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class GameCategory extends Model
{
    use HasFactory;

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_DRAFT = 'draft';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'status',
        'sort_order',
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
        static::saving(function (self $category): void {
            $category->slug = Str::slug((string) ($category->slug ?: $category->name));
        });
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class)->orderBy('sort_order')->orderBy('name');
    }
}
