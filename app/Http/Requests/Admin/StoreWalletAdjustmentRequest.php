<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWalletAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdminUser();
    }

    public function rules(): array
    {
        return [
            'booster_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'booster')),
            ],
            'type' => ['required', Rule::in(['add', 'deduct'])],
            'amount' => ['required', 'numeric', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
