<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Services\Payments\PaymentManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartOrderExtensionCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $order = $this->route('order');

        return $user?->role === 'customer'
            && $order instanceof Order
            && (int) $order->user_id === (int) $user->id;
    }

    public function rules(): array
    {
        return [
            'paymentMethod' => ['required', Rule::in(app(PaymentManager::class)->availableProviderKeys())],
            'target_division' => ['nullable', 'string', 'max:64'],
            'additional_wins' => ['nullable', 'integer', 'min:1', 'max:25'],
            'additional_placement_games' => ['nullable', 'integer', 'min:1', 'max:5'],
            'current_division' => ['nullable', 'string', 'max:64'],
        ];
    }
}
