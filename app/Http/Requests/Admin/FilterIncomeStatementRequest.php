<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\AuthorizesAdminAbilities;
use Illuminate\Foundation\Http\FormRequest;

class FilterIncomeStatementRequest extends FormRequest
{
    use AuthorizesAdminAbilities;

    public function authorize(): bool
    {
        return $this->authorizeAdminAbility('finance.income_statement.view');
    }

    public function rules(): array
    {
        return [
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
        ];
    }
}
