<?php

namespace App\Http\Requests\Admin;

class StoreFaqRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAdminModule('content');
    }

    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:2000'],
            'order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->input('faq');

        if (is_array($payload)) {
            $this->merge($payload);
        }

        $this->merge([
            'question' => $this->trimNullableString('question'),
            'answer' => $this->trimNullableString('answer'),
        ]);
    }
}
