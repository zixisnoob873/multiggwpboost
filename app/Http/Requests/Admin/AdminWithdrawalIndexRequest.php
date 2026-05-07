<?php

namespace App\Http\Requests\Admin;

use App\Models\WithdrawalRequest;
use Illuminate\Validation\Rule;

class AdminWithdrawalIndexRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAdminModule('finance');
    }

    protected function prepareForValidation(): void
    {
        $status = trim((string) $this->input('status', WithdrawalRequest::STATUS_PENDING));

        $this->merge([
            'search' => $this->normalizeSearch(),
            'status' => $status === '' ? WithdrawalRequest::STATUS_PENDING : $status,
            'sort' => trim((string) $this->input('sort', 'created_at')),
            'direction' => $this->normalizeSortDirection(),
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in([
                WithdrawalRequest::STATUS_PENDING,
                WithdrawalRequest::STATUS_APPROVED,
                WithdrawalRequest::STATUS_REJECTED,
                WithdrawalRequest::STATUS_PAID,
            ])],
            'sort' => ['nullable', Rule::in(['created_at', 'amount_cents', 'status', 'processed_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
