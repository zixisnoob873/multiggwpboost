<?php

namespace App\Http\Requests\Admin;

use App\Rules\PublicUrl;

class UpdatePromotionRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAdminModule('marketing');
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'button_text' => ['nullable', 'string', 'max:80'],
            'button_link' => ['nullable', 'string', 'max:2048', new PublicUrl],
            'is_active' => ['nullable', 'boolean'],
            'show_on_homepage' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => $this->trimToNull($this->input('title')),
            'description' => $this->trimToNull($this->input('description')),
            'button_text' => $this->trimToNull($this->input('button_text')),
            'button_link' => $this->trimToNull($this->input('button_link')),
            'sort_order' => is_numeric($this->input('sort_order'))
                ? (int) $this->input('sort_order')
                : null,
        ]);
    }

    protected function trimToNull(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
