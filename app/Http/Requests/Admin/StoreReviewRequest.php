<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdminUser();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'author_name' => trim((string) $this->input('author_name')),
            'service' => trim((string) $this->input('service')),
            'quote' => trim((string) $this->input('quote')),
        ]);
    }

    public function rules(): array
    {
        return [
            'author_name' => ['required', 'string', 'max:120'],
            'service' => ['required', 'string', 'max:120'],
            'quote' => ['required', 'string', 'min:20', 'max:1200'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
