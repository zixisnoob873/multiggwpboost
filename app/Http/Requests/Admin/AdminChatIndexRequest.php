<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderChatThreadType;
use App\Support\OrderStatus;
use Illuminate\Validation\Rule;

class AdminChatIndexRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAdminModule('operations');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->normalizeSearch(),
            'reply_state' => trim((string) $this->input('reply_state', 'all')),
            'lane' => trim((string) $this->input('lane', '')),
            'sort' => trim((string) $this->input('sort', 'latest_activity')),
            'direction' => $this->normalizeSortDirection(),
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(OrderStatus::values())],
            'lane' => ['nullable', Rule::in(OrderChatThreadType::values())],
            'reply_state' => ['nullable', Rule::in(['all', 'needs_reply', 'stale'])],
            'sort' => ['nullable', Rule::in(['latest_activity', 'created_at', 'order_number'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
