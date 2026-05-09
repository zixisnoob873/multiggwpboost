<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\StoreBoosterAction;
use App\Actions\Admin\ToggleBoosterStatusAction;
use App\Actions\Admin\UpdateBoosterAction;
use App\Http\Requests\Admin\AdminBoosterApplicationIndexRequest;
use App\Http\Requests\Admin\AdminBoosterIndexRequest;
use App\Http\Requests\Admin\StoreBoosterRequest;
use App\Http\Requests\Admin\UpdateBoosterApplicationRequest;
use App\Http\Requests\Admin\UpdateBoosterRequest;
use App\Models\AdminAuditLog;
use App\Models\BoosterApplication;
use App\Models\User;
use App\Queries\Admin\BoosterApplicationsQuery;
use App\Queries\Admin\BoosterIndexQuery;
use App\Services\BoosterWalletService;
use App\Support\OrderStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminBoosterController extends AdminController
{
    public function __construct(
        private readonly BoosterApplicationsQuery $boosterApplicationsQuery,
        private readonly BoosterIndexQuery $boosterIndexQuery,
        private readonly BoosterWalletService $boosterWalletService,
        private readonly StoreBoosterAction $storeBoosterAction,
        private readonly UpdateBoosterAction $updateBoosterAction,
        private readonly ToggleBoosterStatusAction $toggleBoosterStatusAction,
    ) {}

    public function applications(AdminBoosterApplicationIndexRequest $request): View
    {
        return $this->renderPage('admin.booster-applications.index', $this->boosterApplicationsQuery->execute($request->validated() + [
            'per_page' => (int) ($request->validated('per_page') ?? 20),
        ]));
    }

    public function index(AdminBoosterIndexRequest $request): View
    {
        return $this->renderPage('admin.boosters.index', $this->boosterIndexQuery->execute($request->validated() + [
            'per_page' => (int) ($request->validated('per_page') ?? 20),
        ]));
    }

    public function create(Request $request): View|RedirectResponse
    {
        $sourceApplication = null;

        if ($request->filled('application')) {
            $sourceApplication = BoosterApplication::query()
                ->with('convertedBooster:id,nickname')
                ->find($request->integer('application'));

            if ($sourceApplication?->isConverted() && $sourceApplication->convertedBooster?->nickname) {
                return redirect()
                    ->route('admin-boosters.edit', ['booster' => $sourceApplication->convertedBooster->nickname])
                    ->with('status', 'This application has already been converted.');
            }
        }

        return $this->renderPage('admin.boosters.create', [
            'sourceApplication' => $sourceApplication,
        ]);
    }

    public function show(User $booster): View
    {
        abort_unless($booster->role === 'booster', 403);

        $booster->load([
            'boosterOrders' => fn ($query) => $query
                ->with(['user:id,name,nickname,email,account_status'])
                ->latest()
                ->limit(8),
            'withdrawalRequests' => fn ($query) => $query->latest()->limit(6),
            'walletAdjustments' => fn ($query) => $query->with('admin:id,name,nickname,email')->latest()->limit(6),
        ])->loadCount('boosterOrders');

        $walletSummary = $this->boosterWalletService->summaryForBooster($booster);

        $boosterOrders = $booster->boosterOrders;
        $boosterOrdersRelation = $booster->boosterOrders();
        $orderStats = [
            'total' => (int) ($booster->booster_orders_count ?? $boosterOrdersRelation->count()),
            'active' => (clone $boosterOrdersRelation)->whereIn('status', OrderStatus::activeValues())->count(),
            'completed' => (clone $boosterOrdersRelation)->where('status', OrderStatus::COMPLETED)->count(),
            'paid_out_cents' => (int) (clone $boosterOrdersRelation)->get()->sum(fn ($order) => (int) $order->resolvedBoosterPayoutCents()),
        ];

        $sourceApplication = BoosterApplication::query()
            ->with(['reviewer:id,name,nickname,email', 'convertedBooster:id,name,nickname,email'])
            ->where('converted_booster_id', $booster->getKey())
            ->latest('converted_at')
            ->first();

        $auditLogs = AdminAuditLog::query()
            ->with('actor:id,name,nickname,email')
            ->where('subject_type', User::class)
            ->where('subject_id', $booster->getKey())
            ->latest('created_at')
            ->limit(10)
            ->get();

        return $this->renderPage('admin.boosters.show', [
            'booster' => $booster,
            'walletSummary' => $walletSummary,
            'recentOrders' => $boosterOrders,
            'withdrawalRequests' => $booster->withdrawalRequests,
            'walletAdjustments' => $booster->walletAdjustments,
            'sourceApplication' => $sourceApplication,
            'orderStats' => $orderStats,
            'auditLogs' => $auditLogs,
        ]);
    }

    public function edit(User $booster): View
    {
        abort_unless($booster->role === 'booster', 403);
        $booster->loadCount('boosterOrders');
        $walletSummary = $this->boosterWalletService->summaryForBooster($booster);

        return $this->renderPage('admin.boosters.edit', [
            'booster' => $booster,
            'walletSummary' => $walletSummary,
        ]);
    }

    public function store(StoreBoosterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            [$booster, $application] = DB::transaction(function () use ($data, $request): array {
                $application = null;

                if (! empty($data['application_id'])) {
                    $application = BoosterApplication::query()
                        ->lockForUpdate()
                        ->findOrFail((int) $data['application_id']);

                    if ($application->isConverted()) {
                        throw ValidationException::withMessages([
                            'application_id' => 'This application has already been converted into a booster account.',
                        ]);
                    }
                }

                $booster = $this->storeBoosterAction->execute($data);

                if ($application instanceof BoosterApplication) {
                    $application->forceFill([
                        'status' => BoosterApplication::STATUS_HIRED,
                        'reviewed_by' => $request->user()?->getKey(),
                        'reviewed_at' => now(),
                        'converted_booster_id' => $booster->getKey(),
                        'converted_at' => now(),
                    ])->save();
                }

                return [$booster, $application];
            }, 3);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        if ($application instanceof BoosterApplication) {
            $this->audit('people', 'booster_application_converted', $application, [
                'converted_booster_id' => $booster->getKey(),
            ], $request);
        }
        $this->audit('people', 'booster_created', $booster, [
            'status' => $booster->account_status,
            'application_id' => $request->integer('application_id') ?: null,
        ], $request);

        return redirect()->route('admin-boosters.index')->with('status', 'Booster created successfully.');
    }

    public function update(UpdateBoosterRequest $request, User $booster): RedirectResponse
    {
        abort_unless($booster->role === 'booster', 403);
        $beforeStatus = $booster->account_status;
        $booster = $this->updateBoosterAction->execute($booster, $request->validated());
        $this->audit('people', 'booster_updated', $booster, [
            'before_status' => $beforeStatus,
            'after_status' => $booster->account_status,
        ], $request);

        return redirect()
            ->route('admin-boosters.edit', ['booster' => $booster->nickname])
            ->with('status', 'Booster updated successfully.');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === 'booster', 403);
        $previousStatus = $user->account_status;
        $this->toggleBoosterStatusAction->execute($user);
        $this->audit('people', 'booster_status_changed', $user, [
            'from' => $previousStatus,
            'to' => $user->fresh()?->account_status,
        ], $request);

        return redirect()->route('admin-boosters.index')->with('status', 'Booster status updated successfully.');
    }

    public function editApplication(BoosterApplication $boosterApplication): View
    {
        return $this->renderPage('admin.booster-applications.edit', [
            'application' => $boosterApplication->load(['reviewer:id,name,email', 'convertedBooster:id,name,nickname,email']),
        ]);
    }

    public function updateApplication(UpdateBoosterApplicationRequest $request, BoosterApplication $boosterApplication): RedirectResponse
    {
        $previousStatus = $boosterApplication->status;
        $boosterApplication->forceFill([
            'status' => $request->validated('status'),
            'admin_notes' => $request->validated('admin_notes'),
            'reviewed_by' => $request->user()?->getKey(),
            'reviewed_at' => now(),
        ])->save();
        $this->audit('people', 'booster_application_updated', $boosterApplication, [
            'from' => $previousStatus,
            'to' => $boosterApplication->status,
        ], $request);

        return redirect()
            ->route('admin-booster-applications.edit', $boosterApplication)
            ->with('status', 'Application updated.');
    }

    public function convertApplication(BoosterApplication $boosterApplication): RedirectResponse
    {
        if ($boosterApplication->isConverted() && $boosterApplication->convertedBooster?->nickname) {
            return redirect()->route('admin-boosters.edit', [
                'booster' => $boosterApplication->convertedBooster->nickname,
            ])->with('status', 'This application has already been converted.');
        }

        return redirect()->route('admin-boosters.create', [
            'application' => $boosterApplication->getKey(),
        ]);
    }
}
