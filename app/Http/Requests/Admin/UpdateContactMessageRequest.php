<?php

namespace App\Http\Requests\Admin;

use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateContactMessageRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAdminModule('people');
    }

    protected function prepareForValidation(): void
    {
        $payload = [
            'status' => trim((string) $this->input('status')),
        ];

        if ($this->has('internal_notes')) {
            $payload['internal_notes'] = $this->normalizeNullableString('internal_notes', 2000);
        }

        $this->merge($payload);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_keys(ContactMessage::statusOptions()))],
            'assigned_admin_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', User::ROLE_SUPER_ADMIN)),
            ],
            'related_order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'related_customer_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'customer')),
            ],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var ContactMessage|null $message */
                $message = $this->route('contactMessage');
                $status = (string) $this->input('status');

                if ($message instanceof ContactMessage && ! $message->canTransitionTo($status)) {
                    $validator->errors()->add('status', 'This status transition is not allowed from the current message state.');
                }

                $relatedOrderId = $this->integer('related_order_id') ?: null;
                $relatedCustomerId = $this->integer('related_customer_id') ?: null;

                if ($relatedOrderId === null || $relatedCustomerId === null) {
                    return;
                }

                $relatedOrder = Order::query()->select(['id', 'user_id'])->find($relatedOrderId);

                if ($relatedOrder instanceof Order && (int) $relatedOrder->user_id !== $relatedCustomerId) {
                    $validator->errors()->add('related_customer_id', 'The related customer must match the selected order owner.');
                }
            },
        ];
    }
}
