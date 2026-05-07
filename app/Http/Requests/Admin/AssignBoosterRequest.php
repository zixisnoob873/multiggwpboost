<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class AssignBoosterRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAdminModule('operations');
    }

    public function rules(): array
    {
        return [
            'booster_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'booster')),
            ],
        ];
    }
}
