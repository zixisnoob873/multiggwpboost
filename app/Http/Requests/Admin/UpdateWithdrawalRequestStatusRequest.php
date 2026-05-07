<?php

namespace App\Http\Requests\Admin;

use App\Models\WithdrawalRequest;
use Illuminate\Validation\Rule;

class UpdateWithdrawalRequestStatusRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAdminModule('finance');
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([WithdrawalRequest::STATUS_APPROVED, WithdrawalRequest::STATUS_REJECTED])],
            'notes' => ['nullable', 'string', 'max:500'],
            'payout_method' => ['nullable', 'string', 'max:120'],
            'transaction_reference' => ['nullable', 'string', 'max:120'],
            'estimated_arrival' => ['nullable', 'string', 'max:160'],
        ];
    }
}
