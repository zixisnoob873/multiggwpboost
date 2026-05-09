<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\AssignBoosterToOrderAction;
use App\Actions\Admin\StoreManualOrderAction;
use App\Actions\Admin\UpdateOrderAction;
use App\Http\Requests\Admin\AdminCustomOrderIndexRequest;
use App\Http\Requests\Admin\AdminOrderIndexRequest;
use App\Http\Requests\Admin\AssignBoosterRequest;
use App\Http\Requests\Admin\StoreManualOrderRequest;
use App\Http\Requests\Admin\UpdateOrderRequest;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Queries\Admin\OrderIndexQuery;
use App\Support\BoostingCatalog;
use App\Support\Security\StoredFilePath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminOrderController extends AdminController
{
    public function __construct(
        protected UpdateOrderAction $updateOrderAction,
        protected StoreManualOrderAction $storeManualOrderAction,
        protected AssignBoosterToOrderAction $assignBoosterToOrderAction,
        protected OrderIndexQuery $orderIndexQuery,
    ) {}

    public function customOrder(AdminCustomOrderIndexRequest $request): View
    {
        $orders = Order::with(['user', 'booster'])
            ->where('is_custom', true)
            ->latest('created_at')
            ->paginate((int) ($request->validated('per_page') ?? 12))
            ->withQueryString();

        return $this->renderPage('admin.custom-order', [
            'orders' => $orders,
            'customers' => $this->customers(),
            'boosters' => $this->boosters(),
            'statusOptions' => self::statusOptions(),
            'paymentStatusOptions' => self::PAYMENT_STATUS_OPTIONS,
        ]);
    }

    public function index(AdminOrderIndexRequest $request): View
    {
        $payload = $this->orderIndexQuery->paginate($request->validated() + [
            'per_page' => (int) ($request->validated('per_page') ?? 25),
        ]);

        return $this->renderPage('admin.total-order', $payload + [
            'customers' => $this->customers(),
            'boosters' => $this->boosters(),
            'statusOptions' => self::statusOptions(),
            'paymentStatusOptions' => self::PAYMENT_STATUS_OPTIONS,
        ]);
    }

    public function edit(Order $order): View
    {
        return $this->show($order);
    }

    public function show(Order $order): View
    {
        $this->authorize('view', $order);
        $order->load(['user', 'booster', 'game', 'gameService'])->loadCount('chatThreads');

        return $this->renderPage('admin.orders.edit', [
            'order' => $order,
            'customers' => $this->customers(),
            'boosters' => $this->boosters(),
            'statusOptions' => self::statusOptions(),
            'paymentStatusOptions' => self::PAYMENT_STATUS_OPTIONS,
            'serviceOptions' => BoostingCatalog::serviceOptions(),
        ]);
    }

    public function export(AdminOrderIndexRequest $request)
    {
        $orders = $this->orderIndexQuery->export($request->validated() + [
            'per_page' => 1000,
        ]);

        return response()->streamDownload(function () use ($orders): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'Order Number',
                'Created At',
                'Customer',
                'Customer Email',
                'Booster',
                'Status',
                'Payment Status',
                'Manual Order',
                'Promo Applied',
                'Game',
                'Service',
                'Addons',
                'Payment Method',
                'Amount',
            ]);

            foreach ($orders as $order) {
                fputcsv($handle, array_map([$this, 'safeCsvCell'], [
                    $order->order_number,
                    optional($order->created_at)->toDateTimeString(),
                    $order->user?->fullIdentity('Customer'),
                    $order->user?->email,
                    $order->booster?->fullIdentity('Unassigned'),
                    $order->statusLabel(),
                    ucfirst((string) $order->payment_status),
                    $order->is_custom ? 'Yes' : 'No',
                    $order->hasPromoApplied() ? 'Yes' : 'No',
                    $order->gameName(),
                    $order->serviceName(),
                    $order->addonsLabel(),
                    $order->paymentMethodLabel(),
                    number_format($order->customerPriceCents() / 100, 2, '.', ''),
                ]));
            }

            fclose($handle);
        }, 'orders-export-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function safeCsvCell(mixed $value): string
    {
        $cell = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $cell) === 1
            ? "'".$cell
            : $cell;
    }

    public function completionProof(Order $order): Response|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $path = StoredFilePath::clean($order->completion_proof_path, 'order-completion-proofs/');

        abort_if($path === null, 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    public function storeManual(StoreManualOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $customer = User::query()->findOrFail($data['user_id']);
        $booster = ! empty($data['booster_id']) ? User::query()->findOrFail($data['booster_id']) : null;

        $order = $this->storeManualOrderAction->execute($customer, $booster, $request->user(), $data);
        $this->audit('operations', 'manual_order_created', $order, [
            'customer_id' => $customer->getKey(),
            'booster_id' => $booster?->getKey(),
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'price_cents' => $order->price_cents,
        ], $request);

        return redirect()
            ->route('admin-custom-order')
            ->with('status', "Order {$order->order_number} created successfully.");
    }

    public function update(UpdateOrderRequest $request, Order $order): RedirectResponse
    {
        $before = [
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'booster_id' => $order->booster_id,
            'user_id' => $order->user_id,
            'price_cents' => $order->price_cents,
        ];
        $this->updateOrderAction->execute($order, $request->validated());
        $order->refresh();
        $this->audit('operations', 'order_updated', $order, [
            'before' => $before,
            'after' => [
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'booster_id' => $order->booster_id,
                'user_id' => $order->user_id,
                'price_cents' => $order->price_cents,
            ],
        ], $request);

        return redirect()->route('admin-orders.edit', $order)->with('status', 'Order updated.');
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): RedirectResponse
    {
        $previousStatus = $order->status;
        $this->updateOrderAction->execute($order, [
            'status' => $request->validated('status'),
            'status_reason' => $request->validated('status_reason'),
            'refund_amount' => $request->validated('refund_amount'),
            'refund_method' => $request->validated('refund_method'),
            'refund_reference' => $request->validated('refund_reference'),
            'refund_arrival_estimate' => $request->validated('refund_arrival_estimate'),
        ]);
        $order->refresh();
        $this->audit('operations', 'order_status_changed', $order, [
            'from' => $previousStatus,
            'to' => $order->status,
        ], $request);

        return back()->with('status', 'Order status updated.');
    }

    public function assignBooster(AssignBoosterRequest $request, Order $order): JsonResponse|RedirectResponse
    {
        $previousBoosterId = $order->booster_id;
        $updatedOrder = $this->assignBoosterToOrderAction
            ->execute($order, $request->validated()['booster_id'] ?? null)
            ->loadMissing(['user', 'booster']);
        $this->audit('operations', 'booster_assignment_changed', $updatedOrder, [
            'from_booster_id' => $previousBoosterId,
            'to_booster_id' => $updatedOrder->booster_id,
        ], $request);

        if (! ($request->expectsJson() || $request->wantsJson() || $request->ajax())) {
            return back()->with('status', 'Booster assignment updated.');
        }

        return response()->json([
            'order' => OrderResource::make($updatedOrder)->resolve(),
        ]);
    }

    protected function customers()
    {
        return User::query()
            ->where('role', 'customer')
            ->orderBy('nickname_normalized')
            ->orderBy('name')
            ->get(['id', 'name', 'first_name', 'last_name', 'nickname', 'email', 'account_status']);
    }

    protected function boosters()
    {
        return User::query()
            ->where('role', 'booster')
            ->orderBy('nickname_normalized')
            ->orderBy('name')
            ->get(['id', 'name', 'first_name', 'last_name', 'nickname', 'email', 'account_status']);
    }
}
