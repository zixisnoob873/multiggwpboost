<?php

namespace App\Http\Requests\Admin;

use App\Models\BlogArticle;
use Illuminate\Validation\Rule;

class AdminBlogArticleIndexRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAdminModule('marketing');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->normalizeSearch(),
            'status' => $this->normalizeNullableString('status', 20),
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in([BlogArticle::STATUS_DRAFT, BlogArticle::STATUS_PUBLISHED])],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
