<?php

namespace App\Http\Requests\Admin;

use App\Http\Controllers\Admin\AdminController;
use App\Support\OrderStatus;
use Illuminate\Validation\Rule;

class AdminOrderIndexRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAdminModule('operations');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tab' => trim((string) $this->input('tab', 'all')),
            'search' => $this->normalizeSearch(),
            'assignment' => trim((string) $this->input('assignment', 'any')),
            'sort' => trim((string) $this->input('sort', 'created_at')),
            'direction' => $this->normalizeSortDirection(),
            'created_from' => $this->normalizeNullableString('created_from', 25),
            'created_to' => $this->normalizeNullableString('created_to', 25),
        ]);
    }

    public function rules(): array
    {
        return [
            'tab' => ['nullable', Rule::in(['all', 'needs_assignment', 'in_progress', 'paused', 'completed', 'manual'])],
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(OrderStatus::values())],
            'payment_status' => ['nullable', Rule::in(array_keys(AdminController::PAYMENT_STATUS_OPTIONS))],
            'assignment' => ['nullable', Rule::in(['any', 'assigned', 'unassigned'])],
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'customer')),
            ],
            'booster_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'booster')),
            ],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'sort' => ['nullable', Rule::in(['created_at', 'order_number', 'price_cents', 'status', 'assigned_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
