<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Services\Payments\PaymentManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartOrderTipCheckoutRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:1', 'max:1000'],
        ];
    }
}
