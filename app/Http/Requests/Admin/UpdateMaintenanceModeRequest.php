<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMaintenanceModeRequest extends FormRequest
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
            'final_confirmation' => ['accepted'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enabled' => filter_var($this->input('enabled'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            'flow_token' => trim((string) $this->input('flow_token')),
            'final_confirmation' => filter_var($this->input('final_confirmation'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
        ]);
    }

    public function messages(): array
    {
        return [
            'flow_token.uuid' => 'The maintenance confirmation session is invalid. Please start again.',
            'final_confirmation.accepted' => 'Confirm the final maintenance-mode action to continue.',
        ];
    }
}
