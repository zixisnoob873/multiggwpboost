<?php

namespace App\Http\Requests\Booster;

use Illuminate\Foundation\Http\FormRequest;

class ClaimBoosterOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'booster';
    }

    public function rules(): array
    {
        return [
            'claim_captcha' => ['required', 'digits:4'],
        ];
    }
}
