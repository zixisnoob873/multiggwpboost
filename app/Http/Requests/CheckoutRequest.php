<?php

namespace App\Http\Requests;

use App\Services\Payments\PaymentManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'customer';
    }

    public function rules(): array
    {
        $providerKeys = app(PaymentManager::class)->providerKeys();

        return [
            'firstName' => ['required', 'string', 'min:2', 'max:80', 'regex:/^[\pL\s.\'-]+$/u'],
            'lastName' => ['required', 'string', 'min:2', 'max:80', 'regex:/^[\pL\s.\'-]+$/u'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'contactMethod' => ['required', Rule::in(['email', 'whatsapp', 'discord'])],
            'whatsapp' => ['nullable', 'required_if:contactMethod,whatsapp', 'string', 'max:20', 'regex:/^\+?[0-9\s().-]{7,20}$/'],
            'discord' => ['nullable', 'required_if:contactMethod,discord', 'string', 'max:32', 'regex:/^[A-Za-z0-9._#-]{2,32}$/'],
            'orderPayload' => ['required', 'string'],
            'promoCode' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'paymentMethod' => ['required', Rule::in($providerKeys)],
            'policy' => ['accepted'],
            'compliance' => ['accepted'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $nullableString = static function (string $value): ?string {
            $trimmed = trim($value);

            return $trimmed !== '' ? $trimmed : null;
        };

        $this->merge([
            'firstName' => trim((string) $this->input('firstName')),
            'lastName' => trim((string) $this->input('lastName')),
            'email' => strtolower(trim((string) $this->input('email'))),
            'whatsapp' => $nullableString((string) $this->input('whatsapp')),
            'discord' => $nullableString((string) $this->input('discord')),
            'promoCode' => ($promo = strtoupper(trim((string) $this->input('promoCode')))) !== '' ? $promo : null,
        ]);
    }

    public function messages(): array
    {
        return [
            'policy.accepted' => 'You must agree to the reschedule and cancellation policy before payment.',
            'compliance.accepted' => 'You must confirm the account-sharing and boosting risks before payment.',
        ];
    }
}
