<?php

namespace App\Http\Controllers;

use App\Actions\ClaimBoosterOrderAction;
use App\Actions\CompleteBoosterOrderAction;
use App\Actions\SubmitWithdrawalRequestAction;
use App\Actions\UpdateBoosterOrderStatusAction;
use App\Http\Controllers\Concerns\CanonicalizesChatRoute;
use App\Http\Requests\Booster\BoosterChatIndexRequest;
use App\Http\Requests\Booster\BoosterOrdersIndexRequest;
use App\Http\Requests\Booster\ClaimBoosterOrderRequest;
use App\Http\Requests\Booster\CompleteBoosterOrderRequest;
use App\Http\Requests\Booster\DropBoosterOrderRequest;
use App\Http\Requests\Booster\StoreCompletionProofRequest;
use App\Http\Requests\Booster\SubmitWithdrawalRequest as SubmitWithdrawalRequestData;
use App\Http\Requests\Booster\UpdateBoosterOrderStatusRequest;
use App\Models\Order;
use App\Queries\BoosterDashboardQuery;
use App\Queries\BoosterOrdersQuery;
use App\Services\BoosterOrderCaptchaService;
use App\Services\BoosterWalletService;
use App\Services\OrderAssignmentService;
use App\Services\Orders\OrderCompletionProofStorageService;
use App\Services\Orders\OrderProgressService;
use App\Support\OrderStatus;
use App\Support\OrderChatViewData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BoosterController extends Controller
{
    use CanonicalizesChatRoute;

    public function __construct(
        protected BoosterWalletService $boosterWalletService,
        protected BoosterOrdersQuery $boosterOrdersQuery,
        protected BoosterDashboardQuery $boosterDashboardQuery,
        protected SubmitWithdrawalRequestAction $submitWithdrawalRequestAction,
        protected ClaimBoosterOrderAction $claimBoosterOrderAction,
        protected OrderAssignmentService $orderAssignmentService,
        protected UpdateBoosterOrderStatusAction $updateBoosterOrderStatusAction,
        protected CompleteBoosterOrderAction $completeBoosterOrderAction,
        protected BoosterOrderCaptchaService $boosterOrderCaptchaService,
        protected OrderProgressService $orderProgressService,
        protected OrderCompletionProofStorageService $orderCompletionProofStorageService,
    ) {}

    public function chats(BoosterChatIndexRequest $request): View
    {
        return view('booster.chats.index', $this->boosterOrdersQuery->chatIndex(Auth::user(), $request->validated()));
    }

    public function showChat(Request $request, Order $order): View|RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->role === 'booster' && $order->booster_id === $user->id, 403);

        if ($redirect = $this->redirectToCanonicalChatRouteIfNeeded($request, $order, 'booster-chats.show')) {
            return $redirect;
        }

        if (! $order->canBoosterOpenWorkspace()) {
            return redirect()->route('booster-orders', ['view' => 'all'])->withErrors([
                'status' => 'Completed and cancelled orders are not available in the booster workspace.',
            ]);
        }

        $order->loadMissing(['user', 'booster']);

        return view('booster.chats.show', [
            'order' => $order,
            'chatView' => OrderChatViewData::make($order, $user),
            'progressForm' => $this->orderProgressService->snapshot($order),
            'dropCaptchaCode' => $this->boosterOrderCaptchaService->issueFreshCode($request->session(), 'drop', $order),
            'completeCaptchaCode' => $this->boosterOrderCaptchaService->issueFreshCode($request->session(), 'complete', $order),
            'openModal' => $this->resolveOpenModal($request),
        ]);
    }

    public function dropOrder(DropBoosterOrderRequest $request, Order $order): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->role === 'booster' && $order->booster_id === $user->id, 403);
        abort_unless($order->canBoosterOpenWorkspace(), 403);

        $data = $request->validated();

        if (! $this->boosterOrderCaptchaService->verify($request->session(), 'drop', $order, $data['drop_captcha'])) {
            return back()->withErrors([
                'drop_captcha' => 'Captcha code did not match. Please try again.',
            ]);
        }

        $existingProofPath = $order->completion_proof_path;

        try {
            $this->orderAssignmentService->releaseToQueue($order, $user);
        } catch (HttpException $exception) {
            return back()->withErrors([
                'drop' => $exception->getMessage(),
            ]);
        }

        $this->orderCompletionProofStorageService->delete($existingProofPath);

        return redirect()->route('booster-claim-orders')->with('status', 'Order dropped and returned to the queue.');
    }

    public function claimOrders(Request $request): View
    {
        $availableOrders = $this->boosterOrdersQuery->claimable();
        $claimCaptchaCodes = $this->boosterOrderCaptchaService->issueFreshCodes($request->session(), 'claim', $availableOrders);

        return view('booster.claim-orders', [
            'availableOrders' => $availableOrders,
            'claimCaptchaCodes' => $claimCaptchaCodes,
        ]);
    }

    public function claimOrder(ClaimBoosterOrderRequest $request, Order $order): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->role === 'booster', 403);

        $data = $request->validated();

        if (! $this->boosterOrderCaptchaService->verify($request->session(), 'claim', $order, $data['claim_captcha'])) {
            return redirect()->route('booster-claim-orders')
                ->withErrors(['claim' => 'Captcha code did not match. Please try again.']);
        }

        try {
            $this->claimBoosterOrderAction->execute($user, $order);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            return redirect()->route('booster-claim-orders')->withErrors(['claim' => $exception->getMessage()]);
        }

        return redirect()->route('booster-claim-orders')->with('status', 'Order claimed successfully.');
    }

    public function dashboard(): View
    {
        return view('booster.dashboard', $this->boosterDashboardQuery->execute(Auth::user()));
    }

    public function orders(BoosterOrdersIndexRequest $request): View
    {
        return view('booster.orders', $this->boosterOrdersQuery->browse(Auth::user(), $request->validated()));
    }

    public function updateOrderStatus(UpdateBoosterOrderStatusRequest $request, Order $order): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->role === 'booster' && $order->booster_id === $user->id, 403);

        try {
            $this->updateBoosterOrderStatusAction->execute($user, $order, $request->validated()['status']);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return back()->with('status', 'Order status updated.');
    }

    public function storeCompletionProof(StoreCompletionProofRequest $request, Order $order): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->role === 'booster' && $order->booster_id === $user->id, 403);

        if (! in_array($order->status, [OrderStatus::IN_PROGRESS, OrderStatus::PAUSED], true)) {
            return back()->withErrors([
                'complete' => 'Only in-progress or paused assigned orders can be completed.',
            ]);
        }

        $data = $request->validated();

        $previousPath = $order->completion_proof_path;

        try {
            $storedPath = $this->orderCompletionProofStorageService->store($data['completion_proof'], $order, $user);
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'completion_proof' => $exception->getMessage(),
            ]);
        }

        $order->forceFill([
            'completion_proof_path' => $storedPath,
            'completion_proof_uploaded_at' => now(),
        ])->save();

        if ($previousPath && $previousPath !== $storedPath) {
            $this->orderCompletionProofStorageService->delete($previousPath);
        }

        return redirect()
            ->route('booster-chats.show', ['order' => $order])
            ->with('status', 'Completion proof uploaded. Finish the captcha to complete the order.')
            ->with('boosterModal', 'boosterCompleteCaptchaModal');
    }

    public function completeOrder(CompleteBoosterOrderRequest $request, Order $order): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->role === 'booster' && $order->booster_id === $user->id, 403);

        $data = $request->validated();

        if (! $this->boosterOrderCaptchaService->verify($request->session(), 'complete', $order, $data['complete_captcha'])) {
            return back()->withErrors([
                'complete_captcha' => 'Captcha code did not match. Please try again.',
            ]);
        }

        try {
            $this->completeBoosterOrderAction->execute($user, $order);
        } catch (HttpException $exception) {
            return back()->withErrors([
                'complete' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('booster-orders', ['view' => 'all'])->with('status', 'Order marked as completed.');
    }

    public function wallet(): View
    {
        $summary = $this->boosterWalletService->summaryForBooster(Auth::user());

        return view('booster.wallet', [
            'currentBalanceCents' => $summary['current_balance_cents'],
            'availableBalanceCents' => $summary['available_balance_cents'],
            'totalEarnedCents' => $summary['total_earned_cents'],
            'totalWithdrawnCents' => $summary['total_withdrawn_cents'],
            'pendingEarningsCents' => $summary['pending_earnings_cents'],
            'pendingWithdrawalCents' => $summary['pending_withdrawal_cents'],
            'totalAdjustmentCents' => $summary['total_adjustment_cents'],
            'boosterPayoutPercentage' => Order::configuredBoosterPayoutPercentage(),
            'balanceSnapshotAt' => $summary['balance_snapshot_at'] ?? null,
            'balanceModel' => $summary['balance_model'] ?? null,
            'completedOrders' => $summary['completed_orders']->take(12),
            'withdrawalRequests' => $summary['withdrawal_requests']->take(10),
            'walletAdjustments' => $summary['wallet_adjustments']->take(10),
        ]);
    }

    public function submitWithdrawalRequest(SubmitWithdrawalRequestData $request): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->role === 'booster', 403);

        $data = $request->validated();

        try {
            $this->submitWithdrawalRequestAction->execute($user, (float) $data['amount']);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            return back()->withInput()->withErrors(['amount' => $exception->getMessage()]);
        }

        return redirect()->route('booster-wallet')->with('status', 'Withdrawal request submitted.');
    }

    protected function resolveOpenModal(Request $request): string
    {
        $errors = $request->session()->get('errors');

        if ($request->session()->has('boosterModal')) {
            return (string) $request->session()->get('boosterModal');
        }

        if ($errors?->has('completion_proof')) {
            return 'boosterCompleteProofModal';
        }

        if ($errors?->has('complete') || $errors?->has('complete_captcha')) {
            return 'boosterCompleteCaptchaModal';
        }

        if ($errors?->has('drop') || $errors?->has('drop_captcha')) {
            return 'boosterDropConfirmModal';
        }

        return '';
    }
}
