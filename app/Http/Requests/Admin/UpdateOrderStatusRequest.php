<?php

namespace App\Http\Requests\Admin;

use App\Models\Order;
use App\Support\OrderStatus;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateOrderStatusRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAdminModule('operations');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => trim((string) $this->input('status')),
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(OrderStatus::values())],
            'status_reason' => ['nullable', 'string', 'max:500'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'refund_method' => ['nullable', 'string', 'max:120'],
            'refund_reference' => ['nullable', 'string', 'max:120'],
            'refund_arrival_estimate' => ['nullable', 'string', 'max:160'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Order|null $order */
                $order = $this->route('order');
                $targetStatus = (string) $this->validated('status');

                if (! $order instanceof Order) {
                    return;
                }

                if (! OrderStatus::canAdminTransition($order->status, $targetStatus)) {
                    $validator->errors()->add('status', 'This status transition is not allowed from the current order state.');
                }

                if ($targetStatus === OrderStatus::REFUNDED && ! $order->canAdminRefund()) {
                    $validator->errors()->add('status', 'Only paid orders can be refunded.');
                }
            },
        ];
    }
}
