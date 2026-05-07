<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class BlogArticle extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'title',
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
            'published_at' => 'datetime',
            'include_in_sitemap' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $article): void {
            $article->title = trim((string) $article->title);
            $article->slug = Str::slug((string) $article->slug);
            $article->excerpt = trim((string) $article->excerpt);
            $article->intro = trim((string) $article->intro);
            $article->body = trim((string) $article->body);
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

    public function scopeVisibleInSitemap(Builder $query): Builder
    {
        return $query
            ->published()
            ->where('include_in_sitemap', true)
            ->where(function (Builder $builder): void {
                $builder
                    ->whereNull('robots')
                    ->orWhere('robots', 'not like', '%noindex%');
            });
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
        return $this->meta_title ?: $this->title;
    }

    public function effectiveMetaDescription(): string
    {
        return $this->meta_description ?: Str::limit($this->excerpt, 130, '');
    }

    public function effectiveCanonicalUrl(): string
    {
        return $this->canonical_url ?: route('blog.show', ['slug' => $this->slug]);
    }

    public function effectiveRobots(): string
    {
        return $this->robots ?: 'index,follow';
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
        ]];

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
}
