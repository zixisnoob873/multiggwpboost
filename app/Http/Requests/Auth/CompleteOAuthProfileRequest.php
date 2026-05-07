<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\HandlesUserNickname;
use Illuminate\Foundation\Http\FormRequest;

class CompleteOAuthProfileRequest extends FormRequest
{
    use HandlesUserNickname;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $pendingProfile = (array) $this->session()->get('oauth.pending_profile', []);
        $providerEmail = strtolower(trim((string) ($pendingProfile['email'] ?? '')));

        $this->normalizeNicknameInput();

        $this->merge([
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
            'email' => $providerEmail !== ''
                ? $providerEmail
                : strtolower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        return array_merge([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ], $this->nicknameRules());
    }

    public function messages(): array
    {
        return array_merge($this->nicknameMessages(), [
            'email.required' => 'An email address is required to finish creating your account.',
            'email.unique' => 'An account with this email already exists. Sign in with your password first, then connect this provider from account settings.',
        ]);
    }
}
