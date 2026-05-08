<?php

namespace App\Services;

use App\Data\PromoCodeValidationResult;
use App\Models\PromoCode;
use App\Models\PromoCodeAddon;
use App\Services\Pricing\PricingCalculator;
use App\Support\BoostingCatalog;
use App\Support\GameCatalog;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PromoCodeService
{
    public function __construct(
        protected PricingCalculator $pricingCalculator,
        protected GameCatalog $gameCatalog,
    ) {}

    public function validateCode(?string $code, float $orderAmount): PromoCodeValidationResult
    {
        $normalizedCode = $this->normalizeCode($code);

        if ($normalizedCode === null) {
            return $this->invalid(['Enter a promo code to continue.']);
        }

        $promoCode = $this->promoCodeFor($normalizedCode);

        return $this->validatePromoCodeModel($promoCode, $orderAmount, $normalizedCode);
    }

    public function resolveCodeForPayload(?string $code, array $orderPayload, ?int $userId = null): PromoCodeValidationResult
    {
        $normalizedCode = $this->normalizeCode($code);

        if ($normalizedCode === null) {
            return $this->invalid(['Enter a promo code to continue.']);
        }

        $promoCode = $this->promoCodeFor($normalizedCode, BoostingCatalog::gameSlugFromPayload($orderPayload));

        return $this->resolvePromoCodeModelForPayload($promoCode, $orderPayload, $normalizedCode, $userId);
    }

    public function applyDiscount(PromoCode|string $code, float $amount): float
    {
        $promoCode = $code instanceof PromoCode
            ? $code
            : PromoCode::query()->where('code', $this->normalizeCode($code))->first();

        if (! $promoCode instanceof PromoCode) {
            throw new ModelNotFoundException('Promo code not found.');
        }

        $amount = $this->roundMoney(max(0, $amount));

        if ($promoCode->type === PromoCode::TYPE_FIXED) {
            return min($amount, $this->roundMoney((float) $promoCode->value));
        }

        if ($promoCode->type === PromoCode::TYPE_PERCENTAGE) {
            return $this->roundMoney($amount * (((float) $promoCode->value) / 100));
        }

        return 0.0;
    }

    public function consumeCode(int $codeId): PromoCode
    {
        return DB::transaction(function () use ($codeId) {
            $promoCode = PromoCode::query()
                ->with('addonRules')
                ->lockForUpdate()
                ->findOrFail($codeId);

            $result = $this->validatePromoCodeModel($promoCode, 1, $promoCode->code);

            if (! $result->valid) {
                throw ValidationException::withMessages([
                    'promoCode' => $result->errors,
                ]);
            }

            $promoCode->increment('used_count');

            return $promoCode->refresh();
        });
    }

    public function consumeValidatedCode(int $codeId, float $orderAmount): PromoCodeValidationResult
    {
        $promoCode = PromoCode::query()
            ->with('addonRules')
            ->lockForUpdate()
            ->find($codeId);

        $result = $this->validatePromoCodeModel($promoCode, $orderAmount, $promoCode?->code);

        if (! $result->valid || ! $promoCode) {
            throw ValidationException::withMessages([
                'promoCode' => $result->errors ?: ['This promo code is no longer available.'],
            ]);
        }

        $promoCode->increment('used_count');

        return $result;
    }

    public function consumeResolvedCode(int $codeId, array $orderPayload, ?int $userId = null): PromoCodeValidationResult
    {
        return DB::transaction(function () use ($codeId, $orderPayload, $userId) {
            $promoCode = PromoCode::query()
                ->with('addonRules')
                ->lockForUpdate()
                ->find($codeId);

            $result = $this->resolvePromoCodeModelForPayload($promoCode, $orderPayload, $promoCode?->code, $userId);

            if (! $result->valid || ! $promoCode) {
                throw ValidationException::withMessages(
                    $result->validationErrors !== []
                        ? $result->validationErrors
                        : ['promoCode' => $result->errors ?: ['This promo code is no longer available.']]
                );
            }

            $promoCode->increment('used_count');

            return $result;
        });
    }

    protected function resolvePromoCodeModelForPayload(
        ?PromoCode $promoCode,
        array $orderPayload,
        ?string $normalizedCode = null,
        ?int $userId = null,
    ): PromoCodeValidationResult {
        $basePayload = $this->authoritativePayload($orderPayload);
        $orderAmount = $this->payloadTotal($basePayload);
        $errors = $this->basicValidationErrors($promoCode, $orderAmount, $userId);

        if ($errors !== []) {
            return $this->invalid($errors, $promoCode, $normalizedCode, $orderAmount, [], $basePayload, $basePayload);
        }

        if ($promoCode?->usesAddonRules()) {
            return $this->resolveAddonPromo($promoCode, $basePayload, $normalizedCode);
        }

        $discountAmount = $this->applyDiscount($promoCode, $orderAmount);
        $discountedTotal = $this->roundMoney(max(0, $orderAmount - $discountAmount));
        $resolvedPayload = $this->decorateResolvedPayload(
            $basePayload,
            $promoCode,
            $orderAmount,
            $discountAmount,
            [],
            [],
            [],
        );

        return new PromoCodeValidationResult(
            valid: true,
            promoCode: $promoCode,
            normalizedCode: $normalizedCode ?? $promoCode?->code,
            orderAmount: $orderAmount,
            discountAmount: $discountAmount,
            discountedTotal: $discountedTotal,
            errors: [],
            validationErrors: [],
            originalOrderPayload: $basePayload,
            resolvedOrderPayload: $resolvedPayload,
            promoAddonAdjustments: [],
            promoManagedAddons: [],
            promoAddedAddons: [],
        );
    }

    protected function resolveAddonPromo(
        PromoCode $promoCode,
        array $basePayload,
        ?string $normalizedCode = null,
    ): PromoCodeValidationResult {
        $ruleErrors = $this->validateAddonRulesForPayload($promoCode, $basePayload);

        if ($ruleErrors !== []) {
            return $this->invalid(
                $ruleErrors,
                $promoCode,
                $normalizedCode,
                $this->payloadTotal($basePayload),
                ['promoCode' => $ruleErrors],
                $basePayload,
                $basePayload,
            );
        }

        $mergedPayload = $this->mergePayloadWithPromoAddons($basePayload, $promoCode);

        try {
            $originalPromoPayload = $this->pricingCalculator->calculatePayloadOrFail($mergedPayload, $this->pricingOptions($mergedPayload));
            $resolvedPromoPayload = $this->pricingCalculator->calculatePayloadOrFail(
                $mergedPayload,
                array_merge($this->pricingOptions($mergedPayload), [
                    'addonPriceOverrides' => $this->addonPriceOverrides($promoCode),
                ]),
            );
        } catch (ValidationException $exception) {
            return $this->invalid(
                $this->flattenValidationErrors($exception->errors()),
                $promoCode,
                $normalizedCode,
                $this->payloadTotal($basePayload),
                $exception->errors(),
                $basePayload,
                $basePayload,
            );
        }

        $promoAddonAdjustments = $this->buildPromoAddonAdjustments($promoCode, $basePayload, $originalPromoPayload, $resolvedPromoPayload);
        $integrityErrors = $this->promoAddonIntegrityErrors($promoAddonAdjustments, $originalPromoPayload, $resolvedPromoPayload);

        if ($integrityErrors !== []) {
            return $this->invalid(
                $integrityErrors,
                $promoCode,
                $normalizedCode,
                $this->payloadTotal($originalPromoPayload),
                ['promoCode' => $integrityErrors],
                $originalPromoPayload,
                $resolvedPromoPayload,
            );
        }

        $managedAddons = collect($promoAddonAdjustments)
            ->pluck('label')
            ->filter()
            ->values()
            ->all();
        $addedAddons = collect($promoAddonAdjustments)
            ->filter(fn (array $adjustment): bool => (bool) ($adjustment['addedByPromo'] ?? false))
            ->pluck('label')
            ->filter()
            ->values()
            ->all();
        $orderAmount = $this->payloadTotal($originalPromoPayload);
        $discountedTotal = $this->payloadTotal($resolvedPromoPayload);
        $discountAmount = $this->roundMoney(max(0, $orderAmount - $discountedTotal));
        $resolvedPayload = $this->decorateResolvedPayload(
            $resolvedPromoPayload,
            $promoCode,
            $orderAmount,
            $discountAmount,
            $promoAddonAdjustments,
            $managedAddons,
            $addedAddons,
        );

        return new PromoCodeValidationResult(
            valid: true,
            promoCode: $promoCode,
            normalizedCode: $normalizedCode ?? $promoCode->code,
            orderAmount: $orderAmount,
            discountAmount: $discountAmount,
            discountedTotal: $discountedTotal,
            errors: [],
            validationErrors: [],
            originalOrderPayload: $originalPromoPayload,
            resolvedOrderPayload: $resolvedPayload,
            promoAddonAdjustments: $promoAddonAdjustments,
            promoManagedAddons: $managedAddons,
            promoAddedAddons: $addedAddons,
        );
    }

    protected function validatePromoCodeModel(?PromoCode $promoCode, float $orderAmount, ?string $normalizedCode = null): PromoCodeValidationResult
    {
        $orderAmount = $this->roundMoney(max(0, $orderAmount));
        $errors = $this->basicValidationErrors($promoCode, $orderAmount);

        if ($promoCode?->usesAddonRules()) {
            $errors[] = 'This promo code requires a checkout payload before it can be applied.';
        }

        if ($errors !== []) {
            return $this->invalid($errors, $promoCode, $normalizedCode, $orderAmount);
        }

        $discountAmount = $this->applyDiscount($promoCode, $orderAmount);
        $discountedTotal = $this->roundMoney(max(0, $orderAmount - $discountAmount));

        return new PromoCodeValidationResult(
            valid: true,
            promoCode: $promoCode,
            normalizedCode: $normalizedCode ?? $promoCode->code,
            orderAmount: $orderAmount,
            discountAmount: $discountAmount,
            discountedTotal: $discountedTotal,
            errors: [],
            validationErrors: [],
        );
    }

    protected function basicValidationErrors(?PromoCode $promoCode, float $orderAmount, ?int $userId = null): array
    {
        $errors = [];

        if (! $promoCode instanceof PromoCode) {
            $errors[] = 'This promo code does not exist.';
        } elseif (! $promoCode->is_active) {
            $errors[] = 'This promo code is disabled.';
        } elseif (! $promoCode->isWithinActiveWindow()) {
            $errors[] = $promoCode->start_at && now()->lt($promoCode->start_at)
                ? 'This promo code is not active yet.'
                : 'This promo code has expired.';
        } elseif (! $promoCode->hasRemainingUses()) {
            $errors[] = 'This promo code has reached its usage limit.';
        } elseif ($orderAmount <= 0) {
            $errors[] = 'A valid order total is required before a promo code can be applied.';
        } elseif (! $this->userIsEligible($promoCode, $userId)) {
            $errors[] = 'You are not eligible to use this promo code.';
        } elseif ($promoCode->usesAddonRules() && $promoCode->addonRules->isEmpty()) {
            $errors[] = 'This addon promo code is not configured correctly.';
        }

        return $errors;
    }

    protected function validateAddonRulesForPayload(PromoCode $promoCode, array $basePayload): array
    {
        $serviceType = BoostingCatalog::normalizeServiceType($basePayload['serviceType'] ?? $basePayload['orderType'] ?? null);
        $errors = [];

        foreach ($promoCode->addonRules as $addonRule) {
            $addonSlug = (string) $addonRule->addon_slug;
            $addonLabel = BoostingCatalog::addonLabelBySlug($addonSlug);

            if (! $addonLabel) {
                $errors[] = "Promo addon [{$addonSlug}] is invalid.";

                continue;
            }

            if (! $serviceType || ! BoostingCatalog::addonSupportsService($addonSlug, $serviceType)) {
                $errors[] = "{$addonLabel} is not available for this service.";
            }
        }

        return array_values(array_unique($errors));
    }

    protected function buildPromoAddonAdjustments(
        PromoCode $promoCode,
        array $basePayload,
        array $originalPromoPayload,
        array $resolvedPromoPayload,
    ): array {
        $baseAddons = BoostingCatalog::normalizeAddons($basePayload['addons'] ?? $basePayload['selectedAddons'] ?? []);
        $originalBreakdown = collect($originalPromoPayload['addonBreakdown'] ?? [])->keyBy('label');
        $resolvedBreakdown = collect($resolvedPromoPayload['addonBreakdown'] ?? [])->keyBy('label');

        return $promoCode->addonRules
            ->map(function (PromoCodeAddon $addonRule) use ($baseAddons, $originalBreakdown, $resolvedBreakdown): array {
                $label = $addonRule->addonLabel();
                $originalItem = $originalBreakdown->get($label, []);
                $resolvedItem = $resolvedBreakdown->get($label, []);

                return [
                    'slug' => $addonRule->addon_slug,
                    'label' => $label,
                    'discountType' => $addonRule->discount_type,
                    'discountValue' => (float) $addonRule->discount_value,
                    'addedByPromo' => ! in_array($label, $baseAddons, true),
                    'promoApplied' => true,
                    'originalAmount' => $this->roundMoney((float) ($originalItem['amount'] ?? 0)),
                    'discountedAmount' => $this->roundMoney((float) ($resolvedItem['amount'] ?? 0)),
                    'originalType' => $originalItem['type'] ?? null,
                    'discountedType' => $resolvedItem['type'] ?? $addonRule->discount_type,
                    'message' => ! in_array($label, $baseAddons, true) ? 'Added via promocode' : 'Promo Applied',
                ];
            })
            ->values()
            ->all();
    }

    protected function promoAddonIntegrityErrors(
        array $promoAddonAdjustments,
        array $originalPromoPayload,
        array $resolvedPromoPayload,
    ): array {
        $errors = [];
        $originalTotal = $this->payloadTotal($originalPromoPayload);
        $resolvedTotal = $this->payloadTotal($resolvedPromoPayload);

        if ($resolvedTotal > $originalTotal + 0.009) {
            $errors[] = 'Promo addon pricing cannot increase the order total.';
        }

        foreach ($promoAddonAdjustments as $adjustment) {
            $originalAmount = (float) ($adjustment['originalAmount'] ?? 0);
            $discountedAmount = (float) ($adjustment['discountedAmount'] ?? 0);
            $label = (string) ($adjustment['label'] ?? 'Addon');

            if ($originalAmount < 0 || $discountedAmount < 0) {
                $errors[] = "{$label} produced an invalid price.";
            }

            if ($discountedAmount > $originalAmount + 0.009) {
                $errors[] = "{$label} promo pricing cannot exceed the addon's standard price.";
            }
        }

        return array_values(array_unique($errors));
    }

    protected function mergePayloadWithPromoAddons(array $basePayload, PromoCode $promoCode): array
    {
        $payload = $this->stripPromoArtifacts($basePayload);
        $selectedAddons = BoostingCatalog::normalizeAddons($payload['selectedAddons'] ?? $payload['addons'] ?? []);
        $promoAddons = $promoCode->addonRules
            ->map(fn (PromoCodeAddon $addonRule): string => $addonRule->addonLabel())
            ->values()
            ->all();
        $mergedAddons = array_values(array_unique([...$selectedAddons, ...$promoAddons]));

        $payload['selectedAddons'] = $mergedAddons;
        $payload['addons'] = $mergedAddons;
        $payload['requestedAddons'] = $mergedAddons;

        return $payload;
    }

    protected function authoritativePayload(array $orderPayload): array
    {
        $payload = $this->stripPromoArtifacts($orderPayload);

        return $this->pricingCalculator->calculatePayloadOrFail($payload, $this->pricingOptions($payload));
    }

    protected function pricingOptions(array $payload): array
    {
        $serviceType = (string) ($payload['serviceType'] ?? $payload['orderType'] ?? '');

        return [
            'allowExtendedRankedWins' => $serviceType === 'Ranked Wins',
        ];
    }

    protected function addonPriceOverrides(PromoCode $promoCode): array
    {
        return $promoCode->addonRules
            ->mapWithKeys(fn (PromoCodeAddon $addonRule): array => [
                $addonRule->addonLabel() => [
                    'type' => $addonRule->discount_type,
                    'value' => (float) $addonRule->discount_value,
                ],
            ])
            ->all();
    }

    protected function decorateResolvedPayload(
        array $payload,
        PromoCode $promoCode,
        float $orderAmount,
        float $discountAmount,
        array $promoAddonAdjustments,
        array $managedAddons,
        array $addedAddons,
    ): array {
        $discountedTotal = $this->roundMoney(max(0, $orderAmount - $discountAmount));
        $payload['promoCode'] = [
            'id' => $promoCode->id,
            'code' => $promoCode->code,
            'type' => $promoCode->type,
            'value' => (float) $promoCode->value,
            'originalTotal' => $orderAmount,
            'discountAmount' => $discountAmount,
            'discountedTotal' => $discountedTotal,
        ];
        $payload['promoAddonAdjustments'] = $promoAddonAdjustments;
        $payload['promoManagedAddons'] = $managedAddons;
        $payload['promoAddedAddons'] = $addedAddons;
        $payload['pricing'] = array_merge((array) ($payload['pricing'] ?? []), [
            'originalTotal' => $orderAmount,
            'discountAmount' => $discountAmount,
            'finalTotal' => $discountedTotal,
            'total' => $payload['pricing']['total'] ?? $discountedTotal,
        ]);

        return $payload;
    }

    protected function stripPromoArtifacts(array $payload): array
    {
        unset(
            $payload['promoCode'],
            $payload['promoAddonAdjustments'],
            $payload['promoManagedAddons'],
            $payload['promoAddedAddons'],
        );

        return BoostingCatalog::sanitizeOrderPayload($payload);
    }

    protected function payloadTotal(array $payload): float
    {
        return $this->roundMoney(max(0, (float) data_get($payload, 'pricing.total', $payload['finalPrice'] ?? 0)));
    }

    protected function flattenValidationErrors(array $errors): array
    {
        return collect($errors)
            ->flatten()
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function userIsEligible(?PromoCode $promoCode, ?int $userId = null): bool
    {
        return true;
    }

    protected function invalid(
        array $errors,
        ?PromoCode $promoCode = null,
        ?string $normalizedCode = null,
        float $orderAmount = 0,
        array $validationErrors = [],
        array $originalOrderPayload = [],
        array $resolvedOrderPayload = [],
    ): PromoCodeValidationResult {
        return new PromoCodeValidationResult(
            valid: false,
            promoCode: $promoCode,
            normalizedCode: $normalizedCode,
            orderAmount: $this->roundMoney(max(0, $orderAmount)),
            discountAmount: 0,
            discountedTotal: $this->roundMoney(max(0, $orderAmount)),
            errors: $errors,
            validationErrors: $validationErrors,
            originalOrderPayload: $originalOrderPayload,
            resolvedOrderPayload: $resolvedOrderPayload,
            promoAddonAdjustments: [],
            promoManagedAddons: [],
            promoAddedAddons: [],
        );
    }

    protected function normalizeCode(?string $code): ?string
    {
        $normalized = Str::upper(trim((string) $code));

        return $normalized !== '' ? $normalized : null;
    }

    protected function roundMoney(float $amount): float
    {
        return round($amount + 0.0000001, 2);
    }

    protected function promoCodeFor(string $normalizedCode, ?string $gameSlug = null): ?PromoCode
    {
        $query = PromoCode::query()
            ->with('addonRules')
            ->where('code', $normalizedCode);

        $gameId = $gameSlug ? $this->gameCatalog->gameId($gameSlug) : null;

        $hasGameId = $this->promoCodesHaveGameId();

        if ($gameId && $hasGameId) {
            $query->where(function ($query) use ($gameId): void {
                $query->whereNull('game_id')->orWhere('game_id', $gameId);
            });
        }

        if ($hasGameId) {
            $query->orderByRaw('CASE WHEN game_id IS NULL THEN 1 ELSE 0 END');
        }

        return $query->first();
    }

    protected function promoCodesHaveGameId(): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasColumn('promo_codes', 'game_id');
        } catch (\Throwable) {
            return false;
        }
    }
}
