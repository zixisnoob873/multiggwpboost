<?php

namespace App\Models;

use App\Support\Security\StoredFilePath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'image_path',
        'button_text',
        'button_link',
        'is_active',
        'show_on_homepage',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'show_on_homepage' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeHomepageVisible(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('show_on_homepage', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function imageUrl(): ?string
    {
        if (! filled($this->image_path)) {
            return null;
        }

        $path = StoredFilePath::clean($this->image_path, [
            'uploads/promotion-images/',
            'promotion_pics/',
        ]);

        if ($path === null) {
            return null;
        }

        if (Storage::disk('private')->exists($path) || Storage::disk('public')->exists($path)) {
            return URL::temporarySignedRoute('promotion-images.show', now()->addMinutes(30), [
                'promotion' => $this,
                'v' => sha1($path),
            ]);
        }

        return null;
    }

    public static function nextSortOrder(): int
    {
        $maxSortOrder = static::query()->max('sort_order');

        return max(0, ((int) $maxSortOrder) + 1);
    }
}
