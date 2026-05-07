<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\AuthorizesAdminAbilities;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemSettingsRequest extends FormRequest
{
    use AuthorizesAdminAbilities;

    public function authorize(): bool
    {
        return $this->authorizeAdminAbility('system.settings.manage');
    }

    public function rules(): array
    {
        return [
            'support_email' => ['nullable', 'email', 'max:255'],
            'ops_notice' => ['nullable', 'string', 'max:500'],
            'customer_order_email_enabled' => ['nullable', 'boolean'],
        ];
    }
}
