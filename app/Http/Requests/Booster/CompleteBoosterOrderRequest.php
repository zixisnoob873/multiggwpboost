<?php

namespace App\Http\Requests\Booster;

use Illuminate\Foundation\Http\FormRequest;

class CompleteBoosterOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'booster';
    }

    public function rules(): array
    {
        return [
            'complete_captcha' => ['required', 'digits:4'],
        ];
    }
}
