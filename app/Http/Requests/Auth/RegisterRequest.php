<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\HandlesUserNickname;
use App\Support\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    use HandlesUserNickname;

    public function authorize(): bool
    {
        return true;
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
            'password' => ['required', 'string', PasswordPolicy::rule(), 'confirmed'],
            'accepted_terms' => ['accepted'],
        ], $this->nicknameRules());
    }

    public function messages(): array
    {
        return array_merge($this->nicknameMessages(), [
            'accepted_terms.accepted' => 'You must agree to the Terms, Privacy, and Refund Policy to create an account.',
        ]);
    }
}
