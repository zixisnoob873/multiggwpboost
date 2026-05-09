<?php

namespace App\Http\Requests\Admin;

use App\Models\BlogArticle;
use App\Rules\PublicUrl;
use App\Support\Cms\BlogArticleContentSerializer;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

abstract class BlogArticleRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAdminModule('marketing');
    }

    protected function prepareForValidation(): void
    {
        $slugSource = $this->input('slug') ?: $this->input('title');
        /** @var BlogArticleContentSerializer $serializer */
        $serializer = app(BlogArticleContentSerializer::class);
        $bodySections = $this->input('body_sections');

        if (! is_array($bodySections)) {
            $bodySections = $serializer->deserialize((string) $this->input('body', ''));
        }

        $categoryName = $this->nullableTrim($this->input('category_name'));
        $categorySlugSource = $this->input('category_slug') ?: $categoryName;

        $this->merge([
            'title' => trim((string) $this->input('title')),
            'slug' => Str::slug((string) $slugSource),
            'category_name' => $categoryName,
            'category_slug' => $this->nullableTrim(Str::slug((string) $categorySlugSource)),
            'tags' => BlogArticle::normalizeTags($this->input('tags_input', $this->input('tags', []))),
            'author_name' => $this->nullableTrim($this->input('author_name')),
            'featured_image_url' => $this->nullableTrim($this->input('featured_image_url')),
            'featured_image_alt' => $this->nullableTrim($this->input('featured_image_alt')),
            'excerpt' => trim((string) $this->input('excerpt')),
            'intro' => trim((string) $this->input('intro')),
            'cta_label' => $this->nullableTrim($this->input('cta_label')),
            'cta_url' => $this->nullableTrim($this->input('cta_url')),
            'meta_title' => $this->nullableTrim($this->input('meta_title')),
            'meta_description' => $this->nullableTrim($this->input('meta_description')),
            'canonical_url' => $this->nullableTrim($this->input('canonical_url')),
            'robots' => $this->nullableTrim($this->input('robots')),
            'body_sections' => collect($bodySections)
                ->map(function (mixed $item): ?array {
                    $heading = trim((string) data_get($item, 'heading'));
                    $body = trim((string) data_get($item, 'body'));

                    if ($heading === '' && $body === '') {
                        return null;
                    }

                    return [
                        'heading' => $heading,
                        'body' => $body,
                    ];
                })
                ->filter()
                ->values()
                ->all(),
            'faq_items' => collect($this->input('faq_items', []))
                ->map(fn (mixed $item): array => [
                    'question' => trim((string) data_get($item, 'question')),
                    'answer' => trim((string) data_get($item, 'answer')),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $this->slugRule()],
            'category_name' => ['nullable', 'string', 'max:120'],
            'category_slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'tags' => ['nullable', 'array', 'max:12'],
            'tags.*' => ['string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'author_name' => ['nullable', 'string', 'max:120'],
            'featured_image_url' => [
                'nullable',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $value = trim((string) $value);

                    if ($value === '' || Str::startsWith($value, ['/']) || filter_var($value, FILTER_VALIDATE_URL)) {
                        return;
                    }

                    $fail('The featured image URL must be an absolute URL or a site-relative path.');
                },
            ],
            'featured_image_alt' => ['nullable', 'string', 'max:255', 'required_with:featured_image_url'],
            'excerpt' => ['required', 'string', 'max:600'],
            'intro' => ['required', 'string', 'max:4000'],
            'body_sections' => ['required', 'array', 'min:1', 'max:12'],
            'body_sections.*.heading' => ['nullable', 'string', 'max:255', 'required_with:body_sections.*.body'],
            'body_sections.*.body' => ['nullable', 'string', 'min:20', 'max:10000', 'required_with:body_sections.*.heading'],
            'faq_items' => ['nullable', 'array', 'max:8'],
            'faq_items.*.question' => ['nullable', 'string', 'max:255', 'required_with:faq_items.*.answer'],
            'faq_items.*.answer' => ['nullable', 'string', 'max:1000', 'required_with:faq_items.*.question'],
            'cta_label' => ['nullable', 'string', 'max:255', 'required_with:cta_url'],
            'cta_url' => [
                'nullable',
                'string',
                'max:2048',
                'required_with:cta_label',
                new PublicUrl,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $value = trim((string) $value);

                    if ($value === '') {
                        return;
                    }

                    if (BlogArticle::pointsToCheckout($value)) {
                        $fail('Blog CTAs must point to a services or content page instead of checkout.');
                    }
                },
            ],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:130'],
            'canonical_url' => ['nullable', 'url', 'max:2048'],
            'robots' => ['nullable', Rule::in(['index,follow', 'noindex,follow', 'noindex,nofollow'])],
            'status' => ['required', Rule::in([BlogArticle::STATUS_DRAFT, BlogArticle::STATUS_PUBLISHED])],
            'published_at' => ['nullable', 'date'],
            'include_in_sitemap' => ['nullable', 'boolean'],
        ];
    }

    abstract protected function slugRule();

    protected function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
