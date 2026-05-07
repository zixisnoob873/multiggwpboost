<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class VerifyMaintenanceModeCaptchaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdminUser();
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'flow_token' => ['required', 'uuid'],
            'captcha' => ['required', 'digits:6'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enabled' => filter_var($this->input('enabled'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            'flow_token' => trim((string) $this->input('flow_token')),
            'captcha' => preg_replace('/\D+/', '', trim((string) $this->input('captcha'))) ?: null,
        ]);
    }

    public function messages(): array
    {
        return [
            'flow_token.uuid' => 'The maintenance confirmation session is invalid. Please start again.',
            'captcha.digits' => 'Enter the 6-digit CAPTCHA exactly as shown.',
        ];
    }
}
