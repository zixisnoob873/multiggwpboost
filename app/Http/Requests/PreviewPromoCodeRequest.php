<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewPromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'promoCode' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'orderPayload' => ['required', 'string', 'max:20000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'promoCode' => strtoupper(trim((string) $this->input('promoCode'))),
        ]);
    }
}
