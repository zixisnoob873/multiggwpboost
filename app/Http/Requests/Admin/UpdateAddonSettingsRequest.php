<?php

namespace App\Http\Requests\Admin;

use App\Support\BoostingCatalog;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAddonSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        $rules = [
            'addon_descriptions' => ['required', 'array'],
        ];

        foreach (BoostingCatalog::addonSlugs() as $slug) {
            $rules["addon_descriptions.{$slug}"] = ['nullable', 'string', 'max:2000'];
        }

        return $rules;
    }
}
