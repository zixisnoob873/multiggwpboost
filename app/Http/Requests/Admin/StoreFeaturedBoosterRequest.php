<?php

namespace App\Http\Requests\Admin;

class StoreFeaturedBoosterRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAdminModule('content');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'region' => ['required', 'string', 'max:255'],
            'platform' => ['required', 'string', 'max:255'],
            'success_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'active_orders' => ['required', 'integer', 'min:0'],
            'is_verified' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->input('featured_booster');

        if (is_array($payload)) {
            $this->merge($payload);
        }

        $this->merge([
            'name' => $this->trimNullableString('name'),
            'region' => $this->trimNullableString('region'),
            'platform' => $this->trimNullableString('platform'),
        ]);
    }
}
