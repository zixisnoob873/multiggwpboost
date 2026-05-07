<?php

namespace App\Http\Requests\Admin;

class AdminPromoCodeIndexRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAdminModule('marketing');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->normalizeSearch(),
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
