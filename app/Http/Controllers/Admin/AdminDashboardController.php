<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\CanonicalizesChatRoute;
use App\Http\Requests\Admin\AdminChatIndexRequest;
use App\Http\Requests\Admin\AdminDashboardRequest;
use App\Models\Order;
use App\Models\User;
use App\Queries\Admin\ChatIndexQuery;
use App\Queries\AdminDashboardQuery;
use App\Services\SystemSettingService;
use App\Support\OrderChatViewData;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminDashboardController extends AdminController
{
    use CanonicalizesChatRoute;

    public function __construct(
        protected AdminDashboardQuery $adminDashboardQuery,
        protected SystemSettingService $systemSettingService,
        protected ChatIndexQuery $chatIndexQuery,
    ) {}

    public function chats(AdminChatIndexRequest $request): View
    {
        return $this->renderPage('admin.chats.index', $this->chatIndexQuery->execute($request->validated() + [
            'per_page' => (int) ($request->validated('per_page') ?? 18),
        ]));
    }

    public function showChat(\Illuminate\Http\Request $request, Order $order): View|RedirectResponse
    {
        if ($redirect = $this->redirectToCanonicalChatRouteIfNeeded($request, $order, 'admin-chats.show')) {
            return $redirect;
        }

        $order->loadMissing(['user', 'booster']);

        return view('admin.chats.show', [
            'order' => $order,
            'chatView' => OrderChatViewData::make($order, request()->user()),
            'boosters' => User::query()
                ->where('role', 'booster')
                ->orderBy('nickname_normalized')
                ->orderBy('name')
                ->get(['id', 'name', 'first_name', 'last_name', 'nickname', 'email', 'account_status']),
        ]);
    }

    public function dashboard(AdminDashboardRequest $request): View
    {
        $period = (string) ($request->validated('period') ?? 'current_month');

        $payload = $this->adminDashboardQuery->execute($period);
        $payload['estimatedNetRevenueCents'] = $payload['totalSaleCents'] - $payload['estimatedBoosterPayoutsCents'];
        $payload['boosterPayoutPercentage'] = \App\Models\Order::configuredBoosterPayoutPercentage();
        $payload['selectedPeriod'] = $period;
        $payload['maintenanceModeEnabled'] = $this->systemSettingService->isMaintenanceModeEnabled();
        $payload['systemSettings'] = $this->systemSettingService->getMany((array) config('admin.settings', []));

        return $this->renderPage('admin.dashboard', $payload);
    }
}
