<?php

namespace App\Http\Requests\Admin;

use App\Http\Controllers\Admin\AdminController;
use Illuminate\Validation\Rule;

class AdminCustomerIndexRequest extends AdminRequest
{
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
            'status' => ['nullable', Rule::in(array_keys(AdminController::ACCOUNT_STATUS_OPTIONS))],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'sort' => ['nullable', Rule::in(['created_at', 'nickname', 'email', 'orders_count'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
