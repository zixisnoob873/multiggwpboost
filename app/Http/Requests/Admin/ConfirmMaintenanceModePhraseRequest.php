<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmMaintenanceModePhraseRequest extends FormRequest
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
            'confirmation_text' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enabled' => filter_var($this->input('enabled'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            'flow_token' => trim((string) $this->input('flow_token')),
            'confirmation_text' => trim((string) $this->input('confirmation_text')),
        ]);
    }

    public function messages(): array
    {
        return [
            'flow_token.uuid' => 'The maintenance confirmation session is invalid. Please start again.',
            'confirmation_text.required' => 'Type CONFIRM exactly to continue.',
        ];
    }
}
