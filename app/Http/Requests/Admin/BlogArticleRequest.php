<?php

namespace App\Http\Requests\Admin;

use App\Models\BlogArticle;
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

        $this->merge([
            'title' => trim((string) $this->input('title')),
            'slug' => Str::slug((string) $slugSource),
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
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $value = trim((string) $value);

                    if ($value === '') {
                        return;
                    }

                    if (Str::startsWith($value, '/')) {
                        if (BlogArticle::pointsToCheckout($value)) {
                            $fail('Blog CTAs must point to a services or content page instead of checkout.');
                        }

                        return;
                    }

                    if (filter_var($value, FILTER_VALIDATE_URL)) {
                        if (BlogArticle::pointsToCheckout($value)) {
                            $fail('Blog CTAs must point to a services or content page instead of checkout.');
                        }

                        return;
                    }

                    $fail('The call to action URL must be an absolute URL or a site-relative path.');
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
