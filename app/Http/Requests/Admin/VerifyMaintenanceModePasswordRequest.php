<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class VerifyMaintenanceModePasswordRequest extends FormRequest
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
            'current_password' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enabled' => filter_var($this->input('enabled'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            'flow_token' => trim((string) $this->input('flow_token')),
            'current_password' => (string) $this->input('current_password'),
        ]);
    }

    public function messages(): array
    {
        return [
            'flow_token.uuid' => 'The maintenance confirmation session is invalid. Please start again.',
            'current_password.required' => 'Enter your current password to continue.',
        ];
    }
}
