<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\SeoMetadata;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SeoMetadata>
 */
class SeoMetadataFactory extends Factory
{
    protected $model = SeoMetadata::class;

    public function definition(): array
    {
        return [
            'seoable_type' => Game::class,
            'seoable_id' => Game::factory(),
            'context' => 'default',
            'meta_title' => fake()->sentence(6),
            'meta_description' => fake()->sentence(14),
            'canonical_url' => null,
            'robots' => 'index,follow',
            'schema_type' => 'WebPage',
            'open_graph_image' => null,
            'include_in_sitemap' => true,
            'changefreq' => 'weekly',
            'priority' => 0.8,
            'metadata' => [],
        ];
    }
}
