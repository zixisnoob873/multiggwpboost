<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\AdminPromoCodeDetailsRequest;
use App\Http\Requests\Admin\AdminPromoCodeIndexRequest;
use App\Http\Requests\Admin\StorePromoCodeRequest;
use App\Http\Requests\Admin\UpdatePromoCodeRequest;
use App\Models\PromoCode;
use App\Queries\Admin\PromoCodeDetailsQuery;
use App\Queries\Admin\PromoCodeIndexQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminPromoCodeController extends AdminController
{
    public function __construct(
        private readonly PromoCodeIndexQuery $promoCodeIndexQuery,
        private readonly PromoCodeDetailsQuery $promoCodeDetailsQuery,
    ) {}

    public function index(AdminPromoCodeIndexRequest $request): View
    {
        return $this->renderPage('admin.promo-codes.index', $this->promoCodeIndexQuery->execute($request));
    }

    public function edit(PromoCode $promoCode): View
    {
        return $this->renderPage('admin.promo-codes.edit', [
            'promoCode' => $promoCode,
        ]);
    }

    public function details(PromoCode $promoCode, AdminPromoCodeDetailsRequest $request): View
    {
        return $this->renderPage('admin.promo-codes.details', $this->promoCodeDetailsQuery->execute($promoCode, $request));
    }

    public function store(StorePromoCodeRequest $request): RedirectResponse
    {
        $promoCode = DB::transaction(function () use ($request): PromoCode {
            $promoCode = PromoCode::query()->create($this->payload(
                $request->validated(),
                $request->boolean('is_active', false),
                $request->boolean('unlimited_validity', false),
            ));

            $this->syncAddonRules($promoCode, $request->validated('addon_rules', []));

            return $promoCode->refresh();
        });
        $this->audit('marketing', 'promo_code_created', $promoCode, [
            'type' => $promoCode->type,
            'is_active' => $promoCode->is_active,
        ], $request);

        return redirect()
            ->route('admin-promo-codes.index')
            ->with('status', "Promo code {$promoCode->code} created successfully.");
    }

    public function update(UpdatePromoCodeRequest $request, PromoCode $promoCode): RedirectResponse
    {
        $before = [
            'type' => $promoCode->type,
            'is_active' => $promoCode->is_active,
            'max_uses' => $promoCode->max_uses,
        ];
        DB::transaction(function () use ($request, $promoCode): void {
            $promoCode->update($this->payload(
                $request->validated(),
                $request->boolean('is_active', false),
                $request->boolean('unlimited_validity', false),
            ));

            $this->syncAddonRules($promoCode, $request->validated('addon_rules', []));
        });
        $this->audit('marketing', 'promo_code_updated', $promoCode, [
            'before' => $before,
            'after' => [
                'type' => $promoCode->type,
                'is_active' => $promoCode->is_active,
                'max_uses' => $promoCode->max_uses,
            ],
        ], $request);

        return redirect()
            ->route('admin-promo-codes.edit', $promoCode)
            ->with('status', "Promo code {$promoCode->code} updated.");
    }

    public function deactivate(Request $request, PromoCode $promoCode): RedirectResponse
    {
        if (! $promoCode->is_active) {
            return redirect()
                ->route('admin-promo-codes.edit', $promoCode)
                ->with('status', "Promo code {$promoCode->code} is already inactive.");
        }

        $promoCode->update(['is_active' => false]);
        $this->audit('marketing', 'promo_code_deactivated', $promoCode, [], $request);

        return redirect()
            ->route('admin-promo-codes.edit', $promoCode)
            ->with('status', "Promo code {$promoCode->code} deactivated.");
    }

    public function destroy(Request $request, PromoCode $promoCode): RedirectResponse
    {
        if (! $promoCode->canBeDeleted()) {
            return redirect()
                ->route('admin-promo-codes.edit', $promoCode)
                ->withErrors([
                    'promoCode' => 'This promo code cannot be deleted after it has been used or attached to an active checkout.',
                ]);
        }

        $code = $promoCode->code;
        $promoCode->delete();
        $this->audit('marketing', 'promo_code_deleted', $code, [], $request);

        return redirect()
            ->route('admin-promo-codes.index')
            ->with('status', "Promo code {$code} deleted.");
    }

    protected function payload(array $validated, bool $isActive, bool $unlimitedValidity): array
    {
        return [
            'code' => $validated['code'],
            'type' => $validated['type'],
            'value' => $validated['type'] === PromoCode::TYPE_ADDON_PROMOCODE
                ? 0
                : ($validated['value'] ?? 0),
            'max_uses' => $validated['max_uses'] ?? null,
            'start_at' => $unlimitedValidity ? null : ($validated['start_at'] ?? null),
            'end_at' => $unlimitedValidity ? null : ($validated['end_at'] ?? null),
            'is_active' => $isActive,
        ];
    }

    protected function syncAddonRules(PromoCode $promoCode, array $addonRules): void
    {
        $promoCode->addonRules()->delete();

        if (! $promoCode->usesAddonRules()) {
            return;
        }

        $promoCode->addonRules()->createMany($addonRules);
    }
}
