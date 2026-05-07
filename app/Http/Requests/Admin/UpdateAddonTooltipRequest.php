<?php

namespace App\Http\Requests\Admin;

class UpdateAddonTooltipRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAdminModule('content');
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->input('addon_tooltip');

        if (is_array($payload)) {
            $this->merge($payload);
        }

        $this->merge([
            'description' => $this->trimNullableString('description'),
        ]);
    }

    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
