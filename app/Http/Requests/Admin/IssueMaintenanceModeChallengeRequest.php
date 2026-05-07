<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class IssueMaintenanceModeChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdminUser();
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enabled' => filter_var($this->input('enabled'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
        ]);
    }
}
