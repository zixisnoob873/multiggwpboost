<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class ResetPricingSettingsRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAdminModule('system') && $this->user()?->adminRole() === 'super_admin';
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'confirmation' => trim((string) $this->input('confirmation', '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'confirmation' => ['required', Rule::in(['RESET PRICING'])],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmation.in' => 'Type RESET PRICING to confirm the reset.',
        ];
    }
}
