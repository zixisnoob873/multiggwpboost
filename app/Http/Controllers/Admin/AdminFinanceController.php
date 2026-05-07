<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ProcessWithdrawalRequestAction;
use App\Http\Requests\Admin\AdminIncomeStatementRequest;
use App\Http\Requests\Admin\AdminWalletAdjustmentIndexRequest;
use App\Http\Requests\Admin\AdminWithdrawalIndexRequest;
use App\Http\Requests\Admin\StoreWalletAdjustmentRequest;
use App\Http\Requests\Admin\UpdateWithdrawalRequestStatusRequest;
use App\Models\BoosterWalletAdjustment;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\BoosterWalletService;
use App\Services\IncomeStatementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminFinanceController extends AdminController
{
    public function __construct(
        protected BoosterWalletService $boosterWalletService,
        protected ProcessWithdrawalRequestAction $processWithdrawalRequestAction,
        protected IncomeStatementService $incomeStatementService
    ) {}

    public function index(): View
    {
        $pendingRequests = WithdrawalRequest::query()->where('status', WithdrawalRequest::STATUS_PENDING)->count();
        $recentWithdrawals = WithdrawalRequest::query()->with('booster')->latest('created_at')->limit(5)->get();
        $recentAdjustments = BoosterWalletAdjustment::query()->with(['booster', 'admin'])->latest('created_at')->limit(5)->get();

        return $this->renderPage('admin.finance.index', [
            'pendingWithdrawalsCount' => $pendingRequests,
            'recentWithdrawals' => $recentWithdrawals,
            'recentAdjustments' => $recentAdjustments,
            'incomeStatementSnapshot' => $this->incomeStatementService->payloadForYear(now()->year),
        ]);
    }

    public function withdrawalRequests(AdminWithdrawalIndexRequest $request): View
    {
        $search = (string) ($request->validated('search') ?? '');
        $sort = (string) ($request->validated('sort') ?? 'created_at');
        $direction = (string) ($request->validated('direction') ?? 'desc');

        $requests = WithdrawalRequest::query()
            ->with('booster')
            ->when(($request->validated('status') ?? null) !== null, fn ($query) => $query->where('status', $request->validated('status')))
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.$search.'%';

                $query->whereHas('booster', fn ($booster) => $booster
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('nickname', 'like', $like));
            });

        $requests = match ($sort) {
            'amount_cents' => $requests->orderBy('amount_cents', $direction),
            'status' => $requests->orderBy('status', $direction)->orderBy('created_at', 'desc'),
            'processed_at' => $requests->orderBy('processed_at', $direction)->orderBy('created_at', 'desc'),
            default => $requests->orderBy('created_at', $direction),
        };

        $requests = $requests
            ->paginate((int) ($request->validated('per_page') ?? 20))
            ->withQueryString();
        $boosters = User::where('role', 'booster')
            ->where('account_status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return $this->renderPage('admin.withdrawal-requests.index', [
            'requests' => $requests,
            'boosters' => $boosters,
            'withdrawalFilters' => $request->validated(),
            'boosterBalances' => $this->boosterWalletService->availableBalanceCentsForBoosters($boosters),
        ]);
    }

    public function walletAdjustments(AdminWalletAdjustmentIndexRequest $request): View
    {
        $search = (string) ($request->validated('search') ?? '');
        $sort = (string) ($request->validated('sort') ?? 'created_at');
        $direction = (string) ($request->validated('direction') ?? 'desc');

        $adjustments = BoosterWalletAdjustment::query()
            ->with(['booster', 'admin'])
            ->when(($request->validated('booster_id') ?? null) !== null, fn ($query) => $query->where('booster_id', $request->validated('booster_id')))
            ->when(($request->validated('type') ?? null) !== null, fn ($query) => $query->where('type', $request->validated('type')))
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.$search.'%';

                $query->where(function ($nested) use ($like): void {
                    $nested
                        ->where('reason', 'like', $like)
                        ->orWhereHas('booster', fn ($booster) => $booster
                            ->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('nickname', 'like', $like));
                });
            });

        $adjustments = match ($sort) {
            'amount_cents' => $adjustments->orderBy('amount_cents', $direction),
            'type' => $adjustments->orderBy('type', $direction)->orderBy('created_at', 'desc'),
            default => $adjustments->orderBy('created_at', $direction),
        };

        return $this->renderPage('admin.wallet-adjustments.index', [
            'adjustments' => $adjustments
                ->paginate((int) ($request->validated('per_page') ?? 20))
                ->withQueryString(),
            'boosters' => User::query()
                ->where('role', 'booster')
                ->where('account_status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'adjustmentFilters' => $request->validated(),
        ]);
    }

    public function incomeStatement(AdminIncomeStatementRequest $request): View
    {
        return $this->renderPage(
            'admin.income-statement',
            $this->incomeStatementService->payloadForYear((int) ($request->validated('year') ?? now()->year))
        );
    }

    public function exportIncomeStatementExcel(AdminIncomeStatementRequest $request)
    {
        $payload = $this->incomeStatementService->payloadForYear((int) ($request->validated('year') ?? now()->year));
        $content = view('admin.income-statement-excel', $payload)->render();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="income-statement-'.$payload['selectedYear'].'.xls"',
        ]);
    }

    public function exportIncomeStatementPdf(AdminIncomeStatementRequest $request): View
    {
        return $this->renderPage(
            'admin.income-statement-pdf',
            $this->incomeStatementService->payloadForYear((int) ($request->validated('year') ?? now()->year))
        );
    }

    public function updateWithdrawalRequestStatus(UpdateWithdrawalRequestStatusRequest $request, WithdrawalRequest $withdrawalRequest): RedirectResponse
    {
        $data = $request->validated();
        $previousStatus = $withdrawalRequest->status;

        $result = $this->processWithdrawalRequestAction->execute(
            $withdrawalRequest,
            $data['status'],
            (int) Auth::id(),
            $data,
        );

        if (! $result['processed']) {
            return redirect()
                ->route('admin-withdrawal-requests.index')
                ->with('status', 'Withdrawal request was already processed.');
        }

        $this->audit('finance', 'withdrawal_request_updated', $result['withdrawalRequest'], [
            'from' => $previousStatus,
            'to' => $result['withdrawalRequest']->status,
            'amount_cents' => $result['withdrawalRequest']->amount_cents,
        ], $request);

        return redirect()
            ->route('admin-withdrawal-requests.index')
            ->with('status', 'Withdrawal request updated.');
    }

    public function storeWalletAdjustment(StoreWalletAdjustmentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $booster = User::findOrFail($data['booster_id']);

        $amountCents = (int) round(((float) $data['amount']) * 100);
        $adminId = (int) Auth::id();
        $createdAdjustment = null;

        try {
            $this->boosterWalletService->withinLockedWallet($booster->id, function (User $lockedBooster, array $summary) use ($data, $amountCents, $adminId, &$createdAdjustment) {
                if ($data['type'] === 'deduct' && $amountCents > (int) ($summary['available_balance_cents'] ?? 0)) {
                    throw ValidationException::withMessages([
                        'amount' => 'Deduction exceeds the booster\'s available wallet balance.',
                    ]);
                }

                $createdAdjustment = BoosterWalletAdjustment::create([
                    'booster_id' => $lockedBooster->id,
                    'admin_id' => $adminId,
                    'type' => $data['type'],
                    'amount_cents' => $amountCents,
                    'reason' => $data['reason'],
                ]);
            });
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        if ($createdAdjustment instanceof BoosterWalletAdjustment) {
            $this->audit('finance', 'wallet_adjustment_created', $createdAdjustment, [
                'booster_id' => $createdAdjustment->booster_id,
                'type' => $createdAdjustment->type,
                'amount_cents' => $createdAdjustment->amount_cents,
            ], $request);
        }

        return back()->with('status', 'Wallet adjustment recorded successfully.');
    }
}
