<?php

namespace App\Http\Requests\Admin;

use App\Support\Cms\PageRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdminUser();
    }

    protected function prepareForValidation(): void
    {
        $content = $this->trimNested($this->input('content', []));

        $this->merge([
            'meta_title' => $this->nullableTrim($this->input('meta_title')),
            'meta_description' => $this->nullableTrim($this->input('meta_description')),
            'canonical_url' => $this->nullableTrim($this->input('canonical_url')),
            'robots' => $this->nullableTrim($this->input('robots')),
            'content' => is_array($content) ? $content : [],
        ]);
    }

    public function rules(): array
    {
        /** @var PageRegistry $pageRegistry */
        $pageRegistry = app(PageRegistry::class);
        $pageKey = (string) $this->route('pageKey');

        return array_merge([
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:130'],
            'canonical_url' => ['nullable', 'url', 'max:2048'],
            'robots' => ['nullable', Rule::in(['index,follow', 'noindex,follow', 'noindex,nofollow'])],
            'include_in_sitemap' => ['nullable', 'boolean'],
        ], $pageRegistry->contentRules($pageKey));
    }

    protected function trimNested(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->trimNested($item), $value);
        }

        return is_string($value) ? trim($value) : $value;
    }

    protected function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
