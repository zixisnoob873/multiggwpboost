<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SeoMetadata extends Model
{
    use HasFactory;

    protected $table = 'seo_metadata';

    protected $fillable = [
        'seoable_type',
        'seoable_id',
        'context',
        'meta_title',
        'meta_description',
        'canonical_url',
        'robots',
        'schema_type',
        'open_graph_image',
        'include_in_sitemap',
        'changefreq',
        'priority',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'include_in_sitemap' => 'boolean',
            'priority' => 'decimal:1',
            'metadata' => 'array',
        ];
    }

    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }

    public function payload(): array
    {
        return [
            'title' => $this->meta_title,
            'description' => $this->meta_description,
            'canonical' => $this->canonical_url,
            'robots' => $this->robots,
            'type' => $this->schema_type,
            'image' => $this->open_graph_image,
            'includeInSitemap' => $this->include_in_sitemap,
            'changefreq' => $this->changefreq,
            'priority' => $this->priority !== null ? (float) $this->priority : null,
            'metadata' => $this->metadata ?? [],
        ];
    }
}
