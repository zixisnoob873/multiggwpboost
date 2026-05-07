<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class AdminDashboardRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAdminModule('dashboard');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'period' => trim((string) $this->input('period', 'current_month')),
        ]);
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::in(['all_time', 'current_month'])],
        ];
    }
}
