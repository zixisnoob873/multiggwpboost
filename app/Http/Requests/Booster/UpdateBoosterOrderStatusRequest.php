<?php

namespace App\Http\Requests\Booster;

use App\Support\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBoosterOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'booster';
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([OrderStatus::IN_PROGRESS])],
        ];
    }
}
