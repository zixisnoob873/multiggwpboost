<?php

namespace App\Http\Requests\Admin;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\Concerns\AuthorizesAdminAbilities;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterPeopleRequest extends FormRequest
{
    use AuthorizesAdminAbilities;

    public function authorize(): bool
    {
        $routeName = (string) optional($this->route())->getName();

        return str_starts_with($routeName, 'admin-boosters')
            ? $this->authorizeAdminAbility('people.boosters.view')
            : $this->authorizeAdminAbility('people.customers.view');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(array_keys(AdminController::ACCOUNT_STATUS_OPTIONS))],
            'sort' => ['nullable', Rule::in(['created_at', 'name', 'email', 'account_status'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
