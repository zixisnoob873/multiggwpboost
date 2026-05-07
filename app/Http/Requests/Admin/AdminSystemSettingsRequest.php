<?php

namespace App\Http\Requests\Admin;

class AdminSystemSettingsRequest extends AdminRequest
{
    protected function prepareForValidation(): void
    {
        $payload = [];

        foreach ((array) config('admin.settings', []) as $key => $definition) {
            $payload[$key] = $this->normalizeNullableString($key, (int) ($definition['max'] ?? 255));
        }

        $this->merge($payload);
    }

    public function rules(): array
    {
        $rules = [];

        foreach ((array) config('admin.settings', []) as $key => $definition) {
            $rules[$key] = ['nullable', 'string', 'max:'.((int) ($definition['max'] ?? 255))];
        }

        return $rules;
    }
}
