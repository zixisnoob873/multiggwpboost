<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\StoreCustomerAction;
use App\Actions\Admin\ToggleCustomerStatusAction;
use App\Actions\Admin\UpdateCustomerAction;
use App\Http\Requests\Admin\AdminCustomerIndexRequest;
use App\Http\Requests\Admin\StoreCustomerRequest;
use App\Http\Requests\Admin\UpdateCustomerRequest;
use App\Models\AdminAuditLog;
use App\Models\ContactMessage;
use App\Models\User;
use App\Queries\Admin\CustomerIndexQuery;
use App\Support\OrderStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminCustomerController extends AdminController
{
    public function __construct(
        private readonly CustomerIndexQuery $customerIndexQuery,
        private readonly StoreCustomerAction $storeCustomerAction,
        private readonly UpdateCustomerAction $updateCustomerAction,
        private readonly ToggleCustomerStatusAction $toggleCustomerStatusAction,
    ) {}

    public function index(AdminCustomerIndexRequest $request): View
    {
        return $this->renderPage('admin.customers.index', $this->customerIndexQuery->execute($request->validated() + [
            'per_page' => (int) ($request->validated('per_page') ?? 20),
        ]));
    }

    public function create(): View
    {
        return $this->renderPage('admin.customers.create');
    }

    public function show(User $user): View
    {
        abort_unless($user->role === 'customer', 403);

        $customer = $user->load([
            'orders' => fn ($query) => $query
                ->with(['booster:id,name,nickname,email,account_status'])
                ->latest()
                ->limit(8),
        ])->loadCount('orders');

        $orders = $customer->orders;
        $ordersRelation = $customer->orders();
        $orderStats = [
            'total' => (int) ($customer->orders_count ?? $ordersRelation->count()),
            'active' => (clone $ordersRelation)->whereIn('status', OrderStatus::activeValues())->count(),
            'completed' => (clone $ordersRelation)->where('status', OrderStatus::COMPLETED)->count(),
            'paid' => (clone $ordersRelation)->where('payment_status', 'paid')->count(),
            'spend_cents' => (int) ((clone $ordersRelation)->sum('price_cents') ?? 0),
        ];

        $contactMessages = ContactMessage::query()
            ->where('related_customer_id', $customer->getKey())
            ->latest()
            ->limit(6)
            ->get();

        $auditLogs = AdminAuditLog::query()
            ->with('actor:id,name,nickname,email')
            ->where('subject_type', User::class)
            ->where('subject_id', $customer->getKey())
            ->latest('created_at')
            ->limit(10)
            ->get();

        return $this->renderPage('admin.customers.show', [
            'customer' => $customer,
            'recentOrders' => $orders,
            'orderStats' => $orderStats,
            'contactMessages' => $contactMessages,
            'auditLogs' => $auditLogs,
        ]);
    }

    public function edit(User $user): View
    {
        abort_unless($user->role === 'customer', 403);
        $user->loadCount('orders');

        return $this->renderPage('admin.customers.edit', [
            'customer' => $user,
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = $this->storeCustomerAction->execute($request->validated());
        $this->audit('people', 'customer_created', $customer, [
            'status' => $customer->account_status,
        ], $request);

        return redirect()->route('admin-customers.index')->with('status', 'Customer created successfully.');
    }

    public function update(UpdateCustomerRequest $request, User $user): RedirectResponse
    {
        abort_unless($user->role === 'customer', 403);
        $beforeStatus = $user->account_status;
        $this->updateCustomerAction->execute($user, $request->validated());
        $this->audit('people', 'customer_updated', $user, [
            'before_status' => $beforeStatus,
            'after_status' => $user->account_status,
        ], $request);

        return redirect()->route('admin-customers.edit', $user)->with('status', 'Customer updated successfully.');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === 'customer', 403);
        $previousStatus = $user->account_status;
        $this->toggleCustomerStatusAction->execute($user);
        $this->audit('people', 'customer_status_changed', $user, [
            'from' => $previousStatus,
            'to' => $user->fresh()?->account_status,
        ], $request);

        return redirect()->route('admin-customers.index')->with('status', 'Customer status updated successfully.');
    }
}
