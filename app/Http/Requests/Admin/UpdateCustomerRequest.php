<?php

namespace App\Http\Requests\Admin;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Concerns\HandlesUserNickname;
use App\Support\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    use HandlesUserNickname;

    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdminUser();
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
        $user = $this->route('user');

        return array_merge([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => ['nullable', 'string', PasswordPolicy::rule()],
            'account_status' => ['required', Rule::in(array_keys(AdminController::ACCOUNT_STATUS_OPTIONS))],
        ], $this->nicknameRules($user?->id));
    }

    public function messages(): array
    {
        return $this->nicknameMessages();
    }
}
