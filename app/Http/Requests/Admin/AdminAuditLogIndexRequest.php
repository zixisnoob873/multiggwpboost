<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class AdminAuditLogIndexRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAdminModule('system');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->normalizeSearch(),
            'sort' => trim((string) $this->input('sort', 'created_at')),
            'direction' => $this->normalizeSortDirection(),
            'created_from' => $this->normalizeNullableString('created_from', 25),
            'created_to' => $this->normalizeNullableString('created_to', 25),
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'module' => ['nullable', Rule::in(array_keys((array) config('admin.modules')))],
            'actor_id' => ['nullable', 'integer', 'exists:users,id'],
            'action' => ['nullable', 'string', 'max:120'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'sort' => ['nullable', Rule::in(['created_at', 'module', 'action'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
