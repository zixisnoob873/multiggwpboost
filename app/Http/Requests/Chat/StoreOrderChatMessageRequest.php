<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:3000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $body = preg_replace("/\r\n?/", "\n", (string) $this->input('body', '')) ?? '';

        $this->merge([
            'body' => trim($body),
        ]);
    }
}
