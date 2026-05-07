<?php

namespace App\Http\Requests\Admin;

class AdminCustomOrderIndexRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAdminModule('operations');
    }

    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
