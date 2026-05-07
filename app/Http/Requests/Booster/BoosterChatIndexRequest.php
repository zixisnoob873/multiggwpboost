<?php

namespace App\Http\Requests\Booster;

use Illuminate\Foundation\Http\FormRequest;

class BoosterChatIndexRequest extends FormRequest
{
    protected $redirectRoute = 'booster-chats';

    public function authorize(): bool
    {
        return $this->user()?->role === 'booster';
    }

    protected function prepareForValidation(): void
    {
        $search = trim((string) $this->input('search', ''));

        $this->merge([
            'search' => $search !== '' ? $search : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
