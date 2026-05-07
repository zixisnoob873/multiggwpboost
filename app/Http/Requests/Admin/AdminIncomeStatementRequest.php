<?php

namespace App\Http\Requests\Admin;

class AdminIncomeStatementRequest extends AdminRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'year' => (int) $this->input('year', now()->year),
        ]);
    }

    public function rules(): array
    {
        return [
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
        ];
    }
}
