<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BoosterApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'nickname' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9 _.-]*$/'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'current_rank' => ['required', 'string', 'max:120'],
            'peak_rank' => ['required', 'string', 'max:120'],
            'average_time' => ['required', 'string', 'max:120'],
            'discord' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._#-]{2,64}$/'],
            'main_account_tracker' => ['required', 'url', 'max:2048'],
            'marketplace_profile' => ['nullable', 'url', 'max:2048'],
            'regions' => ['required', 'array', 'min:1'],
            'regions.*' => ['required', 'in:EU,AP,OCE,NA,MENA,LATAM'],
            'website' => ['nullable', 'max:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'nickname' => trim((string) $this->input('nickname')),
            'email' => strtolower(trim((string) $this->input('email'))),
            'current_rank' => trim((string) $this->input('current_rank')),
            'peak_rank' => trim((string) $this->input('peak_rank')),
            'average_time' => trim((string) $this->input('average_time')),
            'discord' => trim((string) $this->input('discord')),
            'main_account_tracker' => trim((string) $this->input('main_account_tracker')),
            'marketplace_profile' => ($profile = trim((string) $this->input('marketplace_profile'))) !== '' ? $profile : null,
            'website' => trim((string) $this->input('website')),
        ]);
    }

    public function messages(): array
    {
        return [
            'nickname.regex' => 'Nickname / gaming name may only contain letters, numbers, spaces, dots, dashes, and underscores.',
            'discord.regex' => 'Enter a valid Discord username or handle.',
        ];
    }
}
