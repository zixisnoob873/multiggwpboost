<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Support\Cms\PageRegistry;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        /** @var PageRegistry $pageRegistry */
        $pageRegistry = app(PageRegistry::class);

        foreach ($pageRegistry->definitions() as $definition) {
            Page::query()->firstOrCreate(
                ['key' => $definition['key']],
                [
                    'meta_title' => $definition['seo']['title'] ?? null,
                    'meta_description' => $definition['seo']['description'] ?? null,
                    'canonical_url' => $definition['seo']['canonical'] ?? null,
                    'robots' => $definition['seo']['robots'] ?? null,
                    'include_in_sitemap' => (bool) ($definition['seo']['include_in_sitemap'] ?? true),
                    'content' => $definition['content'] ?? [],
                ]
            );
        }
    }
}
