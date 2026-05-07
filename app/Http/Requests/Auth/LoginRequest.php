<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $captcha = preg_replace('/\D+/', '', trim((string) $this->input('captcha'))) ?: null;

        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'captcha' => $captcha,
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }
}
