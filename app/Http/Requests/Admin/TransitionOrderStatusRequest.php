<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\AuthorizesAdminAbilities;
use App\Support\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionOrderStatusRequest extends FormRequest
{
    use AuthorizesAdminAbilities;

    public function authorize(): bool
    {
        return $this->authorizeAdminAbility('operations.orders.manage');
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(OrderStatus::values())],
        ];
    }
}
