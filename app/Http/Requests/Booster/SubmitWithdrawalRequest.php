<?php

namespace App\Http\Requests\Booster;

use Illuminate\Foundation\Http\FormRequest;

class SubmitWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'booster';
    }

    protected function prepareForValidation(): void
    {
        $amount = str_replace([',', '$', ' '], '', (string) $this->input('amount', ''));

        $this->merge([
            'amount' => $amount,
        ]);
    }

    public function rules(): array
    {
        return [
            'amount' => ['bail', 'required', 'numeric', 'min:10', 'max:1000000'],
        ];
    }
}
