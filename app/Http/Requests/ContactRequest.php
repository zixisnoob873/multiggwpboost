<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'order_reference' => ['nullable', 'string', 'max:80'],
            'message' => ['required', 'string', 'min:20', 'max:600'],
            'website' => ['nullable', 'max:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => strtolower(trim((string) $this->input('email'))),
            'order_reference' => ($reference = strtoupper(trim((string) $this->input('order_reference')))) !== '' ? $reference : null,
            'message' => trim((string) $this->input('message')),
            'website' => trim((string) $this->input('website')),
        ]);
    }
}
