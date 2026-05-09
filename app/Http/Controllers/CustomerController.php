<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CanonicalizesChatRoute;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Queries\CustomerDashboardQuery;
use App\Queries\CustomerOrdersQuery;
use App\Services\Orders\RankTrackerActionService;
use App\Services\Payments\PaymentManager;
use App\Support\OrderChatViewData;
use App\Support\OrderStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CustomerController extends Controller
{
    use CanonicalizesChatRoute;

    public function __construct(
        protected CustomerDashboardQuery $customerDashboardQuery,
        protected CustomerOrdersQuery $customerOrdersQuery,
        protected PaymentManager $paymentManager,
        protected RankTrackerActionService $rankTrackerActionService,
    ) {}

    public function dashboard(): View
    {
        return view('user.dashboard', $this->customerDashboardQuery->execute(Auth::user()));
    }

    public function upgradeOrder(): View
    {
        return view('user.upgrade-order');
    }

    public function myOrder(Request $request): RedirectResponse
    {
        $user = auth()->user();

        if ($user?->isAdminUser()) {
            return redirect()->route('admin-chats');
        }

        if ($user?->role === 'booster') {
            return redirect()->route('booster-chats');
        }

        $order = Order::query()
            ->where('user_id', $user?->id)
            ->when($request->filled('id'), fn ($query) => $query->whereKey((int) $request->input('id')))
            ->latest('created_at')
            ->first();

        if ($order) {
            return redirect()->route('user-chats.show', ['order' => $order]);
        }

        return redirect()->route('allorders');
    }

    public function chats(): View|RedirectResponse
    {
        $user = auth()->user();

        if ($user?->isAdminUser()) {
            return redirect()->route('admin-chats');
        }

        if ($user?->role === 'booster') {
            return redirect()->route('booster-chats');
        }

        $order = Order::query()
            ->where('user_id', $user?->id)
            ->latest('created_at')
            ->first();

        if ($order) {
            return redirect()->route('user-chats.show', ['order' => $order]);
        }

        return view('placeholders.communication-removed', [
            'title' => 'GGWP Boost | User',
            'heading' => 'User Chat',
            'description' => 'No active order chat is available yet.',
        ]);
    }

    public function showChat(Request $request, Order $order): View|RedirectResponse
    {
        $user = auth()->user();

        if ($user?->isAdminUser()) {
            return redirect()->route('admin-chats.show', ['order' => $order]);
        }

        if ($user?->role === 'booster' && $order->booster_id === $user->id) {
            if (! OrderStatus::canBoosterOpen($order->status)) {
                return redirect()->route('booster-orders', ['view' => 'all'])
                    ->withErrors(['status' => 'Completed and cancelled orders are not available in the booster workspace.']);
            }

            return redirect()->route('booster-chats.show', ['order' => $order]);
        }

        abort_unless($user && $order->user_id === $user->id, 403);

        if ($redirect = $this->redirectToCanonicalChatRouteIfNeeded($request, $order, 'user-chats.show')) {
            return $redirect;
        }

        $order->loadMissing(['user', 'booster']);
        $paymentProviders = collect($this->paymentManager->allDescriptors())
            ->filter(fn (array $provider) => (bool) ($provider['isAvailable'] ?? true) && (bool) ($provider['isConfigured'] ?? true))
            ->values()
            ->all();
        $defaultPaymentProvider = collect($paymentProviders)
            ->first(fn (array $provider) => (bool) ($provider['isDefault'] ?? false))
            ?? $paymentProviders[0]
            ?? null;
        $rankTrackerActions = $this->rankTrackerActionService->actionState($user, $order, count($paymentProviders) > 0);
        $extensionModal = $this->rankTrackerActionService->extensionModal($order);

        return view('user.chats.show', [
            'order' => $order,
            'chatView' => OrderChatViewData::make($order, $user),
            'rankTrackerActions' => $rankTrackerActions,
            'extensionModal' => $extensionModal,
            'paymentProviders' => $paymentProviders,
            'defaultPaymentProvider' => $defaultPaymentProvider,
        ]);
    }

    public function allOrders(Request $request): View
    {
        $payload = $this->customerOrdersQuery->execute(Auth::user());
        $payload['ordersData'] = OrderResource::collection($payload['orders'] ?? collect())->resolve($request);

        return view('user.orders', $payload);
    }
}
