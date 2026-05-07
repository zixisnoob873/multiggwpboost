<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\AdminContactMessageIndexRequest;
use App\Http\Requests\Admin\UpdateContactMessageRequest;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\User;
use App\Queries\Admin\ContactMessageIndexQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminContactMessageController extends AdminController
{
    public function __construct(
        protected ContactMessageIndexQuery $contactMessageIndexQuery,
    ) {}

    public function index(AdminContactMessageIndexRequest $request): View
    {
        return $this->renderPage('admin.contact-messages.index', $this->contactMessageIndexQuery->execute($request->validated() + [
            'per_page' => (int) ($request->validated('per_page') ?? 25),
        ]) + [
            'admins' => User::query()
                ->where('role', User::ROLE_SUPER_ADMIN)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function edit(ContactMessage $contactMessage): View
    {
        $currentRelatedOrder = $contactMessage->relatedOrder;
        $currentRelatedCustomer = $contactMessage->relatedCustomer;

        $orders = Order::query()
            ->with('user:id,name,email,nickname')
            ->when($currentRelatedOrder, fn ($query) => $query->orWhereKey($currentRelatedOrder->getKey()))
            ->latest('created_at')
            ->limit(150)
            ->get(['id', 'order_number', 'user_id', 'status', 'created_at']);

        $customers = User::query()
            ->where('role', 'customer')
            ->when($currentRelatedCustomer, fn ($query) => $query->orWhereKey($currentRelatedCustomer->getKey()))
            ->orderByDesc('created_at')
            ->limit(150)
            ->get(['id', 'name', 'email', 'nickname']);

        $admins = User::query()
            ->where('role', User::ROLE_SUPER_ADMIN)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return $this->renderPage('admin.contact-messages.edit', [
            'message' => $contactMessage->load(['assignedAdmin:id,name,email', 'relatedOrder.user:id,name,email,nickname', 'relatedCustomer:id,name,email,nickname']),
            'orders' => $orders,
            'customers' => $customers,
            'admins' => $admins,
        ]);
    }

    public function update(UpdateContactMessageRequest $request, ContactMessage $contactMessage): RedirectResponse
    {
        $data = $request->validated();
        $status = (string) ($data['status'] ?? $contactMessage->status);
        $previousStatus = $contactMessage->status;

        $contactMessage->forceFill([
            'status' => $status,
            'assigned_admin_id' => array_key_exists('assigned_admin_id', $data)
                ? $data['assigned_admin_id']
                : $contactMessage->assigned_admin_id,
            'related_order_id' => array_key_exists('related_order_id', $data)
                ? $data['related_order_id']
                : $contactMessage->related_order_id,
            'related_customer_id' => array_key_exists('related_customer_id', $data)
                ? $data['related_customer_id']
                : $contactMessage->related_customer_id,
            'internal_notes' => array_key_exists('internal_notes', $data)
                ? $data['internal_notes']
                : $contactMessage->internal_notes,
            'closed_at' => in_array($status, ContactMessage::resolvedStatuses(), true)
                ? ($contactMessage->closed_at ?? now())
                : null,
        ])->save();
        $this->audit('people', 'contact_message_updated', $contactMessage, [
            'from' => $previousStatus,
            'to' => $contactMessage->status,
            'assigned_admin_id' => $contactMessage->assigned_admin_id,
            'related_order_id' => $contactMessage->related_order_id,
            'related_customer_id' => $contactMessage->related_customer_id,
        ], $request);

        return redirect()
            ->route('admin-contact-messages.edit', $contactMessage)
            ->with('status', 'Contact message updated.');
    }
}
