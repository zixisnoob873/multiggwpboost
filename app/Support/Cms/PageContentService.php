<?php

namespace App\Support\Cms;

use App\Models\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PageContentService
{
    protected ?Collection $pages = null;

    public function __construct(
        protected PageRegistry $pageRegistry,
    ) {}

    public function definitions(): array
    {
        return array_values($this->pageRegistry->definitions());
    }

    public function definition(string $key): array
    {
        return $this->pageRegistry->page($key);
    }

    public function page(string $key): ?Page
    {
        return $this->pages()->get($key);
    }

    public function editableContent(string $key): array
    {
        return $this->mergeContent(
            $this->definition($key)['content'] ?? [],
            $this->page($key)?->content ?? []
        );
    }

    public function publicContent(string $key): array
    {
        return $this->editableContent($key);
    }

    public function save(string $key, array $validated): Page
    {
        $page = Page::query()->updateOrCreate(
            ['key' => $key],
            [
                'meta_title' => $this->nullableTrim($validated['meta_title'] ?? null),
                'meta_description' => $this->nullableTrim($validated['meta_description'] ?? null),
                'canonical_url' => $this->nullableTrim($validated['canonical_url'] ?? null),
                'robots' => $this->nullableTrim($validated['robots'] ?? null),
                'include_in_sitemap' => (bool) ($validated['include_in_sitemap'] ?? false),
                'content' => $this->pageRegistry->normalizeContent($key, $validated['content'] ?? []),
            ]
        );

        $this->pages = null;

        return $page;
    }

    public function seo(string $key, array $overrides = []): array
    {
        $definition = $this->definition($key);
        $page = $this->page($key);
        $defaultSeo = $definition['seo'] ?? [];

        $seo = [
            'title' => $page?->meta_title ?: ($defaultSeo['title'] ?? null),
            'description' => $page?->meta_description ?: ($defaultSeo['description'] ?? null),
            'canonical' => $page?->canonical_url ?: ($defaultSeo['canonical'] ?? $this->routeCanonical($definition)),
            'robots' => $page?->robots ?: ($defaultSeo['robots'] ?? null),
            'type' => $defaultSeo['type'] ?? 'website',
        ];

        foreach ($overrides as $key => $value) {
            if ($value !== null) {
                $seo[$key] = $value;
            }
        }

        return $seo;
    }

    public function pagePath(string $key): string
    {
        $path = parse_url(route($this->definition($key)['route_name']), PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : route($this->definition($key)['route_name']);
    }

    public function includeInSitemap(string $key): bool
    {
        $definition = $this->definition($key);
        $page = $this->page($key);
        $include = $page?->include_in_sitemap;

        if ($include === null) {
            $include = (bool) ($definition['seo']['include_in_sitemap'] ?? true);
        }

        $robots = $page?->robots ?: ($definition['seo']['robots'] ?? '');

        return $include && ! Str::contains(Str::lower((string) $robots), 'noindex');
    }

    public function sitemapPages(): Collection
    {
        return collect($this->pageRegistry->definitions())
            ->filter(fn (array $definition, string $key): bool => $this->includeInSitemap($key))
            ->map(function (array $definition, string $key): array {
                $page = $this->page($key);

                return [
                    'key' => $key,
                    'loc' => route($definition['route_name']),
                    'lastmod' => $page?->updated_at,
                ];
            })
            ->values();
    }

    protected function pages(): Collection
    {
        if ($this->pages === null) {
            $this->pages = Schema::hasTable('pages')
                ? Page::query()->get()->keyBy('key')
                : collect();
        }

        return $this->pages;
    }

    protected function mergeContent(mixed $defaults, mixed $overrides): mixed
    {
        if (! is_array($defaults)) {
            return $overrides ?? $defaults;
        }

        if (! is_array($overrides)) {
            return $defaults;
        }

        if (array_is_list($defaults) && array_is_list($overrides)) {
            return $overrides;
        }

        $merged = $defaults;

        foreach ($overrides as $key => $value) {
            if (array_key_exists($key, $defaults)) {
                $merged[$key] = $this->mergeContent($defaults[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    protected function routeCanonical(array $definition): ?string
    {
        $routeName = $definition['route_name'] ?? null;

        if (! is_string($routeName) || trim($routeName) === '') {
            return null;
        }

        return route($routeName);
    }

    protected function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
