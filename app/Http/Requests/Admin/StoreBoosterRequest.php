<?php

namespace App\Http\Requests\Admin;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Concerns\HandlesUserNickname;
use App\Support\PasswordPolicy;
use Illuminate\Validation\Rule;

class StoreBoosterRequest extends AdminRequest
{
    use HandlesUserNickname;

    public function authorize(): bool
    {
        return $this->authorizeAdminModule('people');
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeNicknameInput();

        $this->merge([
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        return array_merge([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', PasswordPolicy::rule()],
            'account_status' => ['required', Rule::in(array_keys(AdminController::ACCOUNT_STATUS_OPTIONS))],
            'application_id' => ['nullable', 'integer', 'exists:booster_applications,id'],
        ], $this->nicknameRules());
    }

    public function messages(): array
    {
        return $this->nicknameMessages();
    }
}
