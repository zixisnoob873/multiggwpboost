<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class BlogArticle extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'title',
        'game_id',
        'service_id',
        'category_name',
        'category_slug',
        'tags',
        'author_name',
        'featured_image_url',
        'featured_image_alt',
        'slug',
        'excerpt',
        'intro',
        'body',
        'faq_items',
        'cta_label',
        'cta_url',
        'meta_title',
        'meta_description',
        'canonical_url',
        'robots',
        'status',
        'published_at',
        'include_in_sitemap',
    ];

    protected function casts(): array
    {
        return [
            'faq_items' => 'array',
            'tags' => 'array',
            'published_at' => 'datetime',
            'include_in_sitemap' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $article): void {
            $article->title = trim((string) $article->title);
            $article->category_name = self::nullableTrim($article->category_name);
            $article->category_slug = self::nullableTrim($article->category_slug);

            if ($article->category_name !== null && $article->category_slug === null) {
                $article->category_slug = Str::slug($article->category_name);
            }

            if ($article->category_slug !== null) {
                $article->category_slug = Str::slug($article->category_slug);
            }

            $article->slug = Str::slug((string) $article->slug);
            $article->excerpt = trim((string) $article->excerpt);
            $article->intro = trim((string) $article->intro);
            $article->body = trim((string) $article->body);
            $article->tags = self::normalizeTags($article->tags);
            $article->author_name = self::nullableTrim($article->author_name);
            $article->featured_image_url = self::nullableTrim($article->featured_image_url);
            $article->featured_image_alt = self::nullableTrim($article->featured_image_alt);
            $article->cta_label = self::nullableTrim($article->cta_label);
            $article->cta_url = self::nullableTrim($article->cta_url);
            $article->meta_title = self::nullableTrim($article->meta_title);
            $article->meta_description = self::nullableTrim($article->meta_description);
            $article->canonical_url = self::nullableTrim($article->canonical_url);
            $article->robots = self::nullableTrim($article->robots);
            $article->faq_items = self::normalizeFaqItems($article->faq_items);
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeLatestPublished(Builder $query): Builder
    {
        return $query
            ->published()
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    public function scopeInCategory(Builder $query, string $category): Builder
    {
        return $query->where('category_slug', Str::slug($category));
    }

    public function scopeTagged(Builder $query, string $tag): Builder
    {
        return $query->whereJsonContains('tags', Str::slug($tag));
    }

    public function scopeVisibleInSitemap(Builder $query): Builder
    {
        return $query
            ->published()
            ->where('include_in_sitemap', true)
            ->where(function (Builder $builder): void {
                $builder
                    ->whereNull('robots')
                    ->orWhere('robots', 'not like', '%noindex%');
            })
            ->whereDoesntHave('seoMetadata', function (Builder $builder): void {
                $builder
                    ->where('include_in_sitemap', false)
                    ->orWhere('robots', 'like', '%noindex%');
            });
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gameService(): BelongsTo
    {
        return $this->belongsTo(GameService::class, 'service_id');
    }

    public function seoMetadata(): MorphOne
    {
        return $this->morphOne(SeoMetadata::class, 'seoable')->where('context', 'default');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && $this->published_at !== null
            && $this->published_at->isFuture();
    }

    public function publicationStateLabel(): string
    {
        return match (true) {
            $this->isPublished() => 'Published',
            $this->isScheduled() => 'Scheduled',
            default => 'Draft',
        };
    }

    public function publicationStateBadgeClass(): string
    {
        return match ($this->publicationStateLabel()) {
            'Published' => 'text-bg-success',
            'Scheduled' => 'text-bg-warning',
            default => 'text-bg-secondary',
        };
    }

    public function effectiveMetaTitle(): string
    {
        return $this->seoMetadata?->meta_title ?: $this->meta_title ?: $this->title;
    }

    public function effectiveMetaDescription(): string
    {
        return $this->seoMetadata?->meta_description ?: $this->meta_description ?: Str::limit($this->excerpt, 130, '');
    }

    public function effectiveCanonicalUrl(): string
    {
        return $this->seoMetadata?->canonical_url ?: $this->canonical_url ?: route('blog.show', ['slug' => $this->slug]);
    }

    public function effectiveRobots(): string
    {
        return $this->seoMetadata?->robots ?: $this->robots ?: 'index,follow';
    }

    public function effectiveAuthorName(): string
    {
        return $this->author_name ?: 'GGWP-Boost Editorial Team';
    }

    public function categoryLabel(): ?string
    {
        $name = trim((string) $this->category_name);

        if ($name !== '') {
            return $name;
        }

        $slug = trim((string) $this->category_slug);

        return $slug !== '' ? self::tagLabel($slug) : null;
    }

    public function categoryUrl(): ?string
    {
        $slug = trim((string) $this->category_slug);

        return $slug !== '' ? route('blog.category', ['category' => $slug]) : null;
    }

    public function tags(): array
    {
        return self::normalizeTags($this->tags);
    }

    public function tagLabels(): array
    {
        return collect($this->tags())
            ->map(fn (string $tag): string => self::tagLabel($tag))
            ->values()
            ->all();
    }

    public function tagList(): string
    {
        return implode(', ', $this->tagLabels());
    }

    public function tagUrl(string $tag): string
    {
        return route('blog.tag', ['tag' => Str::slug($tag)]);
    }

    public function effectiveFeaturedImageUrl(): ?string
    {
        $url = trim((string) $this->featured_image_url);

        if ($url === '') {
            return null;
        }

        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        if (Str::startsWith($url, ['/'])) {
            return asset(ltrim($url, '/'));
        }

        return null;
    }

    public function effectiveFeaturedImageAlt(): string
    {
        return $this->featured_image_alt ?: "Featured image for {$this->title}";
    }

    public function ctaUrl(): ?string
    {
        return $this->effectiveCtaUrl();
    }

    public function effectiveCtaUrl(): ?string
    {
        $url = trim((string) $this->cta_url);

        if ($url === '') {
            return null;
        }

        if (self::pointsToCheckout($url)) {
            return '/#servicesTab';
        }

        if (Str::startsWith($url, ['/'])) {
            return $url;
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    public function effectiveCtaLabel(): ?string
    {
        $url = $this->effectiveCtaUrl();
        $storedUrl = trim((string) $this->cta_url);

        if ($url === null) {
            return null;
        }

        $label = trim((string) $this->cta_label);
        $targetsServicesHub = $url === '/#servicesTab' || self::pointsToCheckout($storedUrl);

        if ($label === '') {
            return $targetsServicesHub ? 'Explore VALORANT Boosts' : null;
        }

        if ($targetsServicesHub && self::isCheckoutStyleLabel($label)) {
            return 'Explore VALORANT Boosts';
        }

        return $label;
    }

    public function faqItems(): array
    {
        return self::normalizeFaqItems($this->faq_items);
    }

    public function renderedBody(): HtmlString
    {
        return new HtmlString(
            Str::markdown($this->normalizedBody(), [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ])
        );
    }

    public function readingTimeInMinutes(): int
    {
        $faqWords = collect($this->faqItems())
            ->map(fn (array $item): string => trim(($item['question'] ?? '').' '.($item['answer'] ?? '')))
            ->implode(' ');

        $source = implode(' ', [
            $this->title,
            $this->excerpt,
            $this->intro,
            Str::markdown($this->normalizedBody(), [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
            $faqWords,
        ]);

        $wordCount = str_word_count(strip_tags($source));

        return max(1, (int) ceil($wordCount / 220));
    }

    public function schemaGraph(): array
    {
        $graph = [[
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $this->title,
            'description' => $this->effectiveMetaDescription(),
            'datePublished' => $this->published_at?->toIso8601String(),
            'dateModified' => $this->updated_at?->toIso8601String(),
            'mainEntityOfPage' => $this->effectiveCanonicalUrl(),
            'url' => $this->effectiveCanonicalUrl(),
            'publisher' => [
                '@type' => 'Organization',
                'name' => config('app.name', 'GGWP-Boost'),
            ],
            'author' => [
                '@type' => 'Person',
                'name' => $this->effectiveAuthorName(),
            ],
            'articleSection' => $this->categoryLabel(),
            'keywords' => $this->tagLabels(),
        ]];

        if ($this->effectiveFeaturedImageUrl() !== null) {
            $graph[0]['image'] = [
                '@type' => 'ImageObject',
                'url' => $this->effectiveFeaturedImageUrl(),
            ];
        }

        if ($this->faqItems() !== []) {
            $graph[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => collect($this->faqItems())
                    ->map(fn (array $item): array => [
                        '@type' => 'Question',
                        'name' => $item['question'],
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => $item['answer'],
                        ],
                    ])
                    ->values()
                    ->all(),
            ];
        }

        return $graph;
    }

    protected static function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    public static function pointsToCheckout(mixed $value): bool
    {
        $url = trim((string) $value);

        if ($url === '') {
            return false;
        }

        $path = Str::startsWith($url, '/')
            ? parse_url($url, PHP_URL_PATH)
            : parse_url($url, PHP_URL_PATH);

        $path = is_string($path) ? '/'.ltrim($path, '/') : null;

        return $path === '/checkout';
    }

    protected function normalizedBody(): string
    {
        return str_replace(
            [
                '[checkout](/checkout)',
                '[Checkout](/checkout)',
                '](/checkout)',
            ],
            [
                '[services](/#servicesTab)',
                '[services](/#servicesTab)',
                '](/#servicesTab)',
            ],
            $this->body
        );
    }

    protected static function isCheckoutStyleLabel(string $label): bool
    {
        return (bool) preg_match('/\b(checkout|quote|pricing)\b/i', $label);
    }

    protected static function normalizeFaqItems(mixed $items): array
    {
        return collect(Arr::wrap($items))
            ->map(function (mixed $item): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $question = trim((string) ($item['question'] ?? ''));
                $answer = trim((string) ($item['answer'] ?? ''));

                if ($question === '' || $answer === '') {
                    return null;
                }

                return [
                    'question' => $question,
                    'answer' => $answer,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public static function normalizeTags(mixed $items): array
    {
        $items = is_string($items)
            ? preg_split('/[,;\n]+/', $items) ?: []
            : Arr::wrap($items);

        return collect($items)
            ->map(fn (mixed $item): string => Str::slug((string) $item))
            ->filter()
            ->unique()
            ->take(12)
            ->values()
            ->all();
    }

    public static function tagLabel(string $tag): string
    {
        $slug = Str::slug($tag);

        return match ($slug) {
            'valorant' => 'VALORANT',
            'cs2' => 'CS2',
            'mw3' => 'MW3',
            'xp' => 'XP',
            'faceit' => 'FACEIT',
            default => (string) Str::of($slug)->replace('-', ' ')->title(),
        };
    }
}
