<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class AdminWalletAdjustmentIndexRequest extends AdminRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->normalizeSearch(),
            'sort' => trim((string) $this->input('sort', 'created_at')),
            'direction' => $this->normalizeSortDirection(),
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'booster_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'booster')),
            ],
            'type' => ['nullable', Rule::in(['add', 'deduct'])],
            'sort' => ['nullable', Rule::in(['created_at', 'amount_cents', 'type'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
