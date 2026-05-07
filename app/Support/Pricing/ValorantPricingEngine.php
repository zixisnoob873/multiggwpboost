<?php

namespace App\Support\Pricing;

use App\Data\Pricing\PriceCalculationDto;
use App\Support\AgentSelectionValidator;
use App\Support\BoostingCatalog;
use App\Support\GameCatalog;
use App\Support\OrderAddonRules;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ValorantPricingEngine
{
    protected ?array $activePricingSnapshot = null;

    protected ?array $activePricingConfig = null;

    protected ?string $activeGameSlug = null;

    public function __construct(
        protected ValorantPricingConfigRepository $pricingConfigRepository,
        protected GameCatalog $gameCatalog,
    ) {}

    public function calculate(array|PriceCalculationDto $input, array $options = []): array
    {
        $payload = $this->inputPayload($input);
        $this->activeGameSlug = $this->gameCatalog->resolveSlugFromPayload([
            ...$payload,
            'gameSlug' => $options['gameSlug'] ?? $payload['gameSlug'] ?? $payload['game_slug'] ?? $payload['game'] ?? null,
        ]);
        $this->activePricingSnapshot = $this->pricingConfigRepository->current($this->activeGameSlug);
        $this->activePricingConfig = $this->activePricingSnapshot['config'];

        try {
            $normalized = $this->normalizeInput($payload);
            $normalizedOptions = $this->normalizeOptions($options);
            $addonRuleEvaluation = $this->evaluateAddonRules($normalized);
            $normalized = $this->applyResolvedAddonSelections($normalized);
            $validationErrors = $this->validate($normalized, $normalizedOptions, $addonRuleEvaluation);
            $disabledAddons = $addonRuleEvaluation['disabledAddons'] ?? [];
            $requestedAddons = $normalized['selectedAddons'];
            $appliedAddons = array_values(array_diff($requestedAddons, $disabledAddons));

            if ($validationErrors !== []) {
                return $this->buildFailurePayload($normalized, $requestedAddons, $appliedAddons, $disabledAddons, $addonRuleEvaluation, $validationErrors);
            }

            [$basePrice, $rankPath, $rrEligibleFirstStepPrice] = $this->calculateBasePrice($normalized);

            $subtotalBeforeModifiers = $basePrice;
            $subtotalAfterCurrentRr = $this->applyCurrentRrDiscount($normalized, $subtotalBeforeModifiers, $rrEligibleFirstStepPrice);
            $subtotalAfterRr = $this->applyAverageRrModifier($normalized, $subtotalAfterCurrentRr);

            [$addonBreakdown, $addonTotal] = $this->calculateAddons(
                $normalized,
                $subtotalAfterRr,
                $appliedAddons,
                $normalizedOptions['addonPriceOverrides'] ?? [],
            );
            $subtotalAfterAddons = $subtotalAfterRr + $addonTotal;
            $subtotalAfterRegion = $subtotalAfterAddons * $this->regionMultiplier($normalized['region']);
            $subtotalAfterPlatform = $subtotalAfterRegion * $this->platformMultiplier($normalized['platform']);
            $subtotalAfterGlobalModifiers = $subtotalAfterPlatform * $this->boostModeMultiplier($normalized['boostMode']);
            $finalPrice = $this->roundMoney($subtotalAfterGlobalModifiers);

            return array_merge(
                $this->buildBasePayload($normalized, $requestedAddons, $appliedAddons, $disabledAddons, $addonRuleEvaluation),
                [
                    'basePrice' => $this->roundMoney($basePrice),
                    'rankPath' => $rankPath,
                    'addonBreakdown' => $addonBreakdown,
                    'subtotalBeforeModifiers' => $this->roundMoney($subtotalBeforeModifiers),
                    'subtotalAfterRR' => $this->roundMoney($subtotalAfterRr),
                    'subtotalAfterAddons' => $this->roundMoney($subtotalAfterAddons),
                    'subtotalAfterGlobalModifiers' => $this->roundMoney($subtotalAfterGlobalModifiers),
                    'finalPrice' => $finalPrice,
                    'validationErrors' => [],
                    'pricing' => [
                        'base' => $this->roundMoney($basePrice),
                        'basePrice' => $this->roundMoney($basePrice),
                        'subtotal' => $finalPrice,
                        'subtotalBeforeModifiers' => $this->roundMoney($subtotalBeforeModifiers),
                        'subtotalAfterRR' => $this->roundMoney($subtotalAfterRr),
                        'subtotalAfterAddons' => $this->roundMoney($subtotalAfterAddons),
                        'subtotalAfterGlobalModifiers' => $this->roundMoney($subtotalAfterGlobalModifiers),
                        'addons' => $this->roundMoney($addonTotal),
                        'fee' => 0,
                        'tax' => 0,
                        'total' => $finalPrice,
                        'finalPrice' => $finalPrice,
                        'currency' => 'USD',
                    ],
                    'modifiers' => [
                        'region' => [
                            'code' => $normalized['region'],
                            'multiplier' => $this->regionMultiplier($normalized['region']),
                        ],
                        'platform' => [
                            'code' => $normalized['platform'],
                            'multiplier' => $this->platformMultiplier($normalized['platform']),
                        ],
                        'boostMode' => [
                            'code' => $normalized['boostMode'],
                            'label' => $this->boostModeLabel($normalized['boostMode']),
                            'multiplier' => $this->boostModeMultiplier($normalized['boostMode']),
                        ],
                    ],
                ]
            );
        } finally {
            $this->activePricingSnapshot = null;
            $this->activePricingConfig = null;
            $this->activeGameSlug = null;
        }
    }

    public function calculateOrFail(array|PriceCalculationDto $input, array $options = []): array
    {
        $result = $this->calculate($input, $options);

        if ($result['validationErrors'] !== []) {
            throw ValidationException::withMessages($result['validationErrors']);
        }

        return $result;
    }

    protected function inputPayload(array|PriceCalculationDto $input): array
    {
        return $input instanceof PriceCalculationDto
            ? $input->toArray()
            : $input;
    }

    protected function pricing(?string $key = null, mixed $default = null): mixed
    {
        $config = $this->activePricingConfig ??= $this->pricingConfigRepository->config($this->activeGameSlug);

        return $key === null ? $config : Arr::get($config, $key, $default);
    }

    protected function pricingConfigMetadata(): array
    {
        $snapshot = $this->activePricingSnapshot ?? $this->pricingConfigRepository->current($this->activeGameSlug);

        return [
            'gameSlug' => (string) ($snapshot['gameSlug'] ?? $this->activeGameSlug ?? GameCatalog::DEFAULT_GAME_SLUG),
            'version' => (int) ($snapshot['version'] ?? 0),
            'checksum' => (string) ($snapshot['checksum'] ?? ''),
            'source' => (string) ($snapshot['source'] ?? 'unknown'),
            'updatedAt' => $snapshot['updatedAt'] ?? null,
        ];
    }

    protected function normalizeInput(array $input): array
    {
        $serviceType = $this->normalizeServiceType($input['serviceType'] ?? $input['orderType'] ?? null);
        $currentFullRank = $this->normalizeFullRank(
            $input['currentRank'] ?? $input['current_rank'] ?? null,
            $input['currentDivision'] ?? $input['current_division'] ?? null
        );
        $targetFullRank = $this->normalizeTargetRank($serviceType, $input);
        $currentRankData = $this->splitRank($currentFullRank);
        $targetRankData = $this->splitRank($targetFullRank);
        $boostMode = $this->normalizeBoostMode($input['boostMode'] ?? $input['accountType'] ?? $input['playType'] ?? $input['homePlacementPlayType'] ?? $input['homeRankedPlayType'] ?? null);
        $region = $this->normalizeRegion($input['region'] ?? null);
        $platform = $this->normalizePlatform($input['platform'] ?? null);
        $avgRrPerWin = $this->normalizeAverageRr($input['avgRRPerWin'] ?? $input['averageRR'] ?? null);
        $selectedAddons = BoostingCatalog::normalizeAddons($input['selectedAddons'] ?? $input['addons'] ?? []);
        $agentSelections = AgentSelectionValidator::inspectPayload($input);
        $currentRr = $this->normalizeInteger($input['currentRR'] ?? $input['current_rr'] ?? null);
        $numberOfWins = $this->normalizeInteger($input['numberOfWins'] ?? $input['wins'] ?? null);
        $numberOfPlacementGames = $this->normalizeInteger($input['numberOfPlacementGames'] ?? $input['placementGames'] ?? $input['games'] ?? null);

        return [
            'serviceType' => $serviceType,
            'serviceKind' => $this->pricing("services.{$serviceType}.kind"),
            'currentFullRank' => $currentFullRank,
            'currentRank' => $currentRankData['tier'],
            'currentDivision' => $currentRankData['division'],
            'targetFullRank' => $targetFullRank,
            'targetRank' => $targetRankData['tier'],
            'targetDivision' => $targetRankData['division'],
            'currentRR' => $currentRr,
            'avgRRPerWin' => $avgRrPerWin,
            'region' => $region,
            'platform' => $platform,
            'boostMode' => $boostMode,
            'numberOfWins' => $numberOfWins,
            'numberOfPlacementGames' => $numberOfPlacementGames,
            'selectedAddons' => $selectedAddons,
            'agentSelections' => $agentSelections,
            'specificAgents' => $agentSelections['specificAgents']['uuids'] ?? [],
            'oneTrickAgent' => $agentSelections['oneTrickAgent']['uuids'] ?? [],
        ];
    }

    protected function validate(array $data, array $options = [], array $addonRuleEvaluation = []): array
    {
        $errors = [];
        $supportedServices = array_keys($this->pricing('services', []));

        if (! in_array($data['serviceType'], $supportedServices, true)) {
            $errors['serviceType'][] = 'Select a valid service type.';
        }

        if (! $data['currentFullRank']) {
            $errors['currentRank'][] = 'Select a valid current rank.';
        }

        if (! in_array($data['region'], array_keys($this->pricing('modifiers.region', [])), true)) {
            $errors['region'][] = 'Select a valid region.';
        }

        if (! in_array($data['platform'], array_keys($this->pricing('modifiers.platform', [])), true)) {
            $errors['platform'][] = 'Select a valid platform.';
        }

        if (! in_array($data['boostMode'], array_keys($this->pricing('modifiers.boost_mode', [])), true)) {
            $errors['boostMode'][] = 'Select a valid boost mode.';
        }

        if ($data['serviceKind'] === 'rank_boost' || $data['serviceKind'] === 'radiant_boost') {
            if (! $data['targetFullRank']) {
                $errors['targetRank'][] = 'Select a valid target rank.';
            } elseif ($this->rankIndex($data['targetFullRank']) <= $this->rankIndex($data['currentFullRank'])) {
                $errors['targetRank'][] = 'Target rank must be higher than current rank.';
            }

            if ($data['avgRRPerWin'] === null) {
                $errors['avgRRPerWin'][] = 'Select a valid average RR per win option.';
            }

            if ($data['serviceKind'] === 'rank_boost' && ($data['currentRR'] === null || $data['currentRR'] < 0 || $data['currentRR'] > 100)) {
                $errors['currentRR'][] = 'Current RR must be between 0 and 100.';
            }
        }

        if ($data['serviceKind'] === 'ranked_wins') {
            if (($data['numberOfWins'] ?? 0) < 1) {
                $errors['numberOfWins'][] = 'Wins needed must be at least 1.';
            }

            if (! ($options['allowExtendedRankedWins'] ?? false) && ($data['numberOfWins'] ?? 0) > 5) {
                $errors['numberOfWins'][] = 'Wins needed must be between 1 and 5.';
            }
        }

        if ($data['serviceKind'] === 'placement_matches') {
            if (($data['numberOfPlacementGames'] ?? 0) < 1 || ($data['numberOfPlacementGames'] ?? 0) > 5) {
                $errors['numberOfPlacementGames'][] = 'Placement matches must be between 1 and 5.';
            }
        }

        foreach (($addonRuleEvaluation['validationErrors'] ?? []) as $selectionKey => $messages) {
            if ($messages !== []) {
                $errors[$selectionKey] = array_merge($errors[$selectionKey] ?? [], $messages);
            }
        }

        foreach (AgentSelectionValidator::validateSelections(
            $data['agentSelections'] ?? [],
            $data['selectedAddons'] ?? [],
            $addonRuleEvaluation['disabledAddons'] ?? []
        ) as $selectionKey => $messages) {
            if ($messages !== []) {
                $errors[$selectionKey] = array_merge($errors[$selectionKey] ?? [], $messages);
            }
        }

        return OrderAddonRules::uniqueValidationErrors($errors);
    }

    protected function buildFailurePayload(
        array $normalized,
        array $requestedAddons,
        array $appliedAddons,
        array $disabledAddons,
        array $addonRuleEvaluation,
        array $validationErrors
    ): array {
        return array_merge(
            $this->buildBasePayload($normalized, $requestedAddons, $appliedAddons, $disabledAddons, $addonRuleEvaluation),
            [
                'basePrice' => 0,
                'rankPath' => [],
                'addonBreakdown' => [],
                'subtotalBeforeModifiers' => 0,
                'subtotalAfterRR' => 0,
                'subtotalAfterAddons' => 0,
                'subtotalAfterGlobalModifiers' => 0,
                'finalPrice' => 0,
                'validationErrors' => $validationErrors,
                'pricing' => [
                    'base' => 0,
                    'basePrice' => 0,
                    'subtotal' => 0,
                    'subtotalBeforeModifiers' => 0,
                    'subtotalAfterRR' => 0,
                    'subtotalAfterAddons' => 0,
                    'subtotalAfterGlobalModifiers' => 0,
                    'addons' => 0,
                    'fee' => 0,
                    'tax' => 0,
                    'total' => 0,
                    'finalPrice' => 0,
                    'currency' => 'USD',
                ],
                'modifiers' => [
                    'region' => [
                        'code' => $normalized['region'],
                        'multiplier' => $this->regionMultiplier($normalized['region']),
                    ],
                    'platform' => [
                        'code' => $normalized['platform'],
                        'multiplier' => $this->platformMultiplier($normalized['platform']),
                    ],
                    'boostMode' => [
                        'code' => $normalized['boostMode'],
                        'label' => $this->boostModeLabel($normalized['boostMode']),
                        'multiplier' => $this->boostModeMultiplier($normalized['boostMode']),
                    ],
                ],
            ]
        );
    }

    protected function buildBasePayload(
        array $normalized,
        array $requestedAddons,
        array $appliedAddons,
        array $disabledAddons,
        array $addonRuleEvaluation = []
    ): array {
        return [
            'gameSlug' => $this->activeGameSlug ?? GameCatalog::DEFAULT_GAME_SLUG,
            'game' => $this->gameCatalog->gameName($this->activeGameSlug),
            'serviceType' => $normalized['serviceType'],
            'orderType' => $normalized['serviceType'],
            'currentRank' => $normalized['currentRank'],
            'targetRank' => $normalized['targetRank'],
            'currentDivision' => $normalized['currentFullRank'],
            'desiredDivision' => $this->desiredDivisionDisplay($normalized),
            'currentRR' => $this->displayCurrentRr($normalized),
            'avgRRPerWin' => $normalized['avgRRPerWin'],
            'averageRR' => $this->averageRrDisplay($normalized),
            'region' => $normalized['region'],
            'platform' => $normalized['platform'],
            'boostMode' => $normalized['boostMode'],
            'accountType' => $this->boostModeLabel($normalized['boostMode']),
            'numberOfWins' => $normalized['numberOfWins'],
            'numberOfPlacementGames' => $normalized['numberOfPlacementGames'],
            'requestedAddons' => $requestedAddons,
            'selectedAddons' => $requestedAddons,
            'addons' => $appliedAddons,
            'disabledAddons' => $disabledAddons,
            'disabledAddonReasons' => $addonRuleEvaluation['disabledAddonReasons'] ?? [],
            'selfPlayUnavailable' => (bool) ($addonRuleEvaluation['selfPlayUnavailable'] ?? false),
            'selfPlayDisabledByCurrentRank' => (bool) ($addonRuleEvaluation['selfPlayDisabledByCurrentRank'] ?? false),
            'selfPlayDisabledByTargetRank' => (bool) ($addonRuleEvaluation['selfPlayDisabledByTargetRank'] ?? false),
            'selfPlayUnavailableMessage' => $addonRuleEvaluation['selfPlayUnavailableMessage'] ?? null,
            'specificAgents' => $normalized['specificAgents'],
            'oneTrickAgent' => $normalized['oneTrickAgent'],
            'pricingConfig' => $this->pricingConfigMetadata(),
            'updatedAt' => now()->toIso8601String(),
        ];
    }

    protected function calculateBasePrice(array $data): array
    {
        return match ($data['serviceKind']) {
            'placement_matches' => $this->calculatePlacementBasePrice($data),
            'ranked_wins' => $this->calculateRankedWinsBasePrice($data),
            'radiant_boost', 'rank_boost' => $this->calculateRankBoostBasePrice($data),
            default => [0.0, [], 0.0],
        };
    }

    protected function calculatePlacementBasePrice(array $data): array
    {
        $unitPrice = $this->lookupBasePrice('Placement Matches', $data['currentFullRank']);
        $quantity = (int) ($data['numberOfPlacementGames'] ?? 0);
        $basePrice = $unitPrice * $quantity;

        return [
            $basePrice,
            [[
                'label' => $data['currentFullRank'],
                'unitPrice' => $this->roundMoney($unitPrice),
                'quantity' => $quantity,
                'price' => $this->roundMoney($basePrice),
            ]],
            0.0,
        ];
    }

    protected function calculateRankedWinsBasePrice(array $data): array
    {
        $unitPrice = $this->lookupBasePrice('Ranked Wins', $data['currentFullRank']);
        $quantity = (int) ($data['numberOfWins'] ?? 0);
        $basePrice = $unitPrice * $quantity;

        return [
            $basePrice,
            [[
                'label' => $data['currentFullRank'],
                'unitPrice' => $this->roundMoney($unitPrice),
                'quantity' => $quantity,
                'price' => $this->roundMoney($basePrice),
            ]],
            0.0,
        ];
    }

    protected function calculateRankBoostBasePrice(array $data): array
    {
        $rankOrder = $this->pricing('rank_order', []);
        $currentIndex = $this->rankIndex($data['currentFullRank']);
        $targetIndex = $this->rankIndex($data['targetFullRank']);
        $basePrice = 0.0;
        $steps = [];
        $firstStepPrice = 0.0;

        for ($index = $currentIndex; $index < $targetIndex; $index += 1) {
            $fromRank = $rankOrder[$index];
            $toRank = $rankOrder[$index + 1];
            $stepPrice = $this->rankBoostStepPrice($fromRank, $toRank);

            if ($index === $currentIndex) {
                $firstStepPrice = $stepPrice;
            }

            $steps[] = [
                'from' => $fromRank,
                'to' => $toRank,
                'price' => $this->roundMoney($stepPrice),
            ];
            $basePrice += $stepPrice;
        }

        return [$basePrice, $steps, $firstStepPrice];
    }

    protected function applyCurrentRrDiscount(array $data, float $subtotal, float $firstStepPrice): float
    {
        if (! in_array($data['serviceKind'], ['rank_boost', 'radiant_boost'], true)) {
            return $subtotal;
        }

        if (($data['currentRR'] ?? 0) < (int) $this->pricing('rr_rules.current_rr_discount_threshold', 50)) {
            return $subtotal;
        }

        return max(0, $subtotal - ($firstStepPrice * (float) $this->pricing('rr_rules.first_step_discount_multiplier', 0.50)));
    }

    protected function applyAverageRrModifier(array $data, float $subtotal): float
    {
        if (! in_array($data['serviceKind'], ['rank_boost', 'radiant_boost'], true)) {
            return $subtotal;
        }

        $modifier = (float) $this->pricing('rr_rules.avg_rr_modifiers.'.$data['avgRRPerWin'], 1.00);

        return $subtotal * $modifier;
    }

    protected function calculateAddons(
        array $data,
        float $subtotalAfterRr,
        array $addons,
        array $addonPriceOverrides = [],
    ): array {
        $definitions = $this->pricing('addons', []);
        $addonBreakdown = [];
        $total = 0.0;

        foreach ($addons as $label) {
            $definition = $definitions[$label] ?? null;

            if (! is_array($definition)) {
                continue;
            }

            $standardPricing = $this->standardAddonPricing($label, $definition, $data, $subtotalAfterRr);
            $override = $addonPriceOverrides[$label] ?? null;
            $type = (string) ($override['type'] ?? $standardPricing['type']);
            $value = $override !== null
                ? ($override['value'] ?? null)
                : ($standardPricing['value'] ?? null);
            $amount = $override !== null
                ? $this->overrideAddonAmount($label, $data, $subtotalAfterRr, $override)
                : $standardPricing['amount'];

            $addonBreakdown[] = array_filter([
                'label' => $label,
                'type' => $type,
                'amount' => $this->roundMoney($amount),
                'value' => $value !== null ? (float) $value : null,
                'pricingSource' => $override !== null ? 'override' : 'catalog',
                'originalType' => $override !== null ? $standardPricing['type'] : null,
                'originalAmount' => $override !== null ? $this->roundMoney($standardPricing['amount']) : null,
                'originalValue' => $override !== null && ($standardPricing['value'] ?? null) !== null
                    ? (float) $standardPricing['value']
                    : null,
            ], static fn (mixed $value): bool => $value !== null);

            $total += $amount;
        }

        return [$addonBreakdown, $total];
    }

    protected function bonusWinAmount(array $data): float
    {
        $rankForBonus = match ($data['serviceKind']) {
            'rank_boost', 'radiant_boost' => $data['targetFullRank'],
            default => $data['currentFullRank'],
        };

        return $this->lookupBasePrice('Ranked Wins', $rankForBonus);
    }

    protected function rankBoostStepPrice(string $fromRank, string $toRank): float
    {
        $specialKey = "{$fromRank}->{$toRank}";
        $specialSteps = $this->pricing('special_rank_boost_steps', []);

        if (array_key_exists($specialKey, $specialSteps)) {
            return (float) $specialSteps[$specialKey];
        }

        return $this->lookupBasePrice('Rank Boosting', $fromRank);
    }

    protected function lookupBasePrice(string $serviceType, ?string $rank): float
    {
        if (! $rank) {
            return 0.0;
        }

        return (float) $this->pricing("base_prices.{$serviceType}.{$rank}", 0);
    }

    protected function normalizeServiceType(mixed $value): ?string
    {
        $needle = Str::of((string) $value)->trim()->lower()->value();

        return collect(array_keys($this->pricing('services', [])))
            ->first(fn (string $service) => Str::lower($service) === $needle);
    }

    protected function normalizeTargetRank(?string $serviceType, array $input): ?string
    {
        if ($serviceType === 'Radiant Boost') {
            return 'Radiant';
        }

        return $this->normalizeFullRank(
            $input['targetRank'] ?? $input['desiredRank'] ?? $input['target_rank'] ?? null,
            $input['targetDivision'] ?? $input['desiredDivision'] ?? $input['desired_division'] ?? null
        );
    }

    protected function normalizeFullRank(mixed $rank, mixed $division): ?string
    {
        $rankValue = Str::of((string) $rank)->trim()->replaceMatches('/\s+/', ' ')->value();
        $divisionValue = Str::of((string) $division)->trim()->upper()->value();

        $candidates = array_values(array_filter([
            $rankValue,
            $divisionValue,
            $rankValue !== '' && $divisionValue !== '' && ! str_contains($rankValue, ' ')
                ? trim("{$rankValue} {$this->normalizeDivisionToken($divisionValue)}")
                : null,
        ]));

        foreach ($candidates as $candidate) {
            $canonical = $this->canonicalizeRankCandidate($candidate);

            foreach ($this->pricing('rank_order', []) as $supportedRank) {
                if ($canonical === $supportedRank) {
                    return $supportedRank;
                }
            }
        }

        return null;
    }

    protected function splitRank(?string $fullRank): array
    {
        if (! $fullRank) {
            return ['tier' => null, 'division' => null];
        }

        $parts = preg_split('/\s+/', $fullRank) ?: [];

        if (count($parts) === 1) {
            return ['tier' => $parts[0], 'division' => null];
        }

        return [
            'tier' => $parts[0],
            'division' => $parts[1] ?? null,
        ];
    }

    protected function normalizeBoostMode(mixed $value): ?string
    {
        $needle = Str::of((string) $value)
            ->trim()
            ->lower()
            ->replace(['-', '/', '\\'], '_')
            ->replace(' ', '_')
            ->replaceMatches('/_+/', '_')
            ->trim('_')
            ->value();

        return match ($needle) {
            'account_shared', 'normal' => 'normal',
            'self_play', 'duo', 'duo_self_play', 'self_play_duo' => 'self_play',
            default => $this->boostModeCodeFromLabel($needle),
        };
    }

    protected function normalizeRegion(mixed $value): ?string
    {
        $region = Str::upper(Str::of((string) $value)->trim()->value());

        return array_key_exists($region, $this->pricing('modifiers.region', [])) ? $region : null;
    }

    protected function normalizePlatform(mixed $value): ?string
    {
        $platform = Str::of((string) $value)->trim()->value();
        $platform = strtoupper($platform) === 'PC'
            ? 'PC'
            : Str::title(Str::lower($platform));

        return array_key_exists($platform, $this->pricing('modifiers.platform', [])) ? $platform : null;
    }

    protected function normalizeAverageRr(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $rawValue = Str::of((string) $value)->trim()->value();
        $labelKey = $this->averageRrKeyFromLabel($rawValue);

        if ($labelKey !== null) {
            return $labelKey;
        }

        $raw = Str::of($rawValue)->upper()->replace(' OR LOWER', '')->replace(' OR MORE', '')->replace('+', '')->value();

        if (preg_match('/\d+/', $raw, $matches) !== 1) {
            return null;
        }

        $number = (int) $matches[0];

        return match (true) {
            $number <= 16 => '16',
            $number <= 18 => '18',
            $number >= 20 => '20',
            default => '18',
        };
    }

    protected function normalizeInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    protected function normalizeOptions(array $options): array
    {
        return [
            'allowExtendedRankedWins' => (bool) ($options['allowExtendedRankedWins'] ?? false),
            'addonPriceOverrides' => $this->normalizeAddonPriceOverrides($options['addonPriceOverrides'] ?? []),
        ];
    }

    protected function normalizeAddonPriceOverrides(mixed $overrides): array
    {
        $normalized = [];

        foreach ((array) $overrides as $addon => $override) {
            $label = BoostingCatalog::normalizeAddon($addon);

            if (! $label || ! is_array($override)) {
                continue;
            }

            $type = Str::lower(trim((string) ($override['type'] ?? '')));
            if (! in_array($type, ['free', 'percentage', 'fixed'], true)) {
                continue;
            }

            $normalized[$label] = [
                'type' => $type,
                'value' => is_numeric($override['value'] ?? null)
                    ? max(0, (float) $override['value'])
                    : 0.0,
            ];
        }

        return $normalized;
    }

    protected function standardAddonPricing(
        string $label,
        array $definition,
        array $data,
        float $subtotalAfterRr,
    ): array {
        $type = (string) ($definition['type'] ?? 'free');
        $amount = match ($type) {
            'percent' => $subtotalAfterRr * (float) ($definition['value'] ?? 0),
            'bonus_win' => $this->bonusWinAmount($data),
            default => 0.0,
        };

        return [
            'label' => $label,
            'type' => $type,
            'amount' => $amount,
            'value' => $type === 'percent'
                ? (float) ($definition['value'] ?? 0)
                : null,
        ];
    }

    protected function overrideAddonAmount(
        string $label,
        array $data,
        float $subtotalAfterRr,
        array $override,
    ): float {
        return match ((string) ($override['type'] ?? 'free')) {
            'percentage' => max(0, $subtotalAfterRr) * ((float) ($override['value'] ?? 0) / 100),
            'fixed' => max(0, (float) ($override['value'] ?? 0)),
            default => 0.0,
        };
    }

    protected function normalizeDivisionToken(string $value): string
    {
        return match (Str::upper($value)) {
            '1' => 'I',
            '2' => 'II',
            '3' => 'III',
            default => Str::upper($value),
        };
    }

    protected function canonicalizeRankCandidate(string $value): string
    {
        $candidate = Str::of($value)->trim()->replaceMatches('/\s+/', ' ')->value();
        $candidate = preg_replace('/\b1\b/u', 'I', $candidate) ?: $candidate;
        $candidate = preg_replace('/\b2\b/u', 'II', $candidate) ?: $candidate;
        $candidate = preg_replace('/\b3\b/u', 'III', $candidate) ?: $candidate;
        $candidate = Str::title(Str::lower($candidate));
        $candidate = preg_replace('/\bIii\b/u', 'III', $candidate) ?: $candidate;
        $candidate = preg_replace('/\bIi\b/u', 'II', $candidate) ?: $candidate;
        $candidate = preg_replace('/\bI\b/u', 'I', $candidate) ?: $candidate;

        return $candidate;
    }

    protected function boostModeCodeFromLabel(string $needle): ?string
    {
        foreach ((array) $this->pricing('labels.boost_modes', []) as $code => $label) {
            $labelNeedle = Str::of((string) $label)
                ->trim()
                ->lower()
                ->replace(['-', '/', '\\'], '_')
                ->replace(' ', '_')
                ->replaceMatches('/_+/', '_')
                ->trim('_')
                ->value();

            if ($needle === $labelNeedle || $needle === (string) $code) {
                return (string) $code;
            }
        }

        return null;
    }

    protected function averageRrKeyFromLabel(string $value): ?string
    {
        $needle = Str::of($value)->lower()->replaceMatches('/\s+/', ' ')->trim()->value();

        foreach ((array) $this->pricing('labels.avg_rr', []) as $key => $label) {
            $labelNeedle = Str::of((string) $label)->lower()->replaceMatches('/\s+/', ' ')->trim()->value();

            if ($needle === $labelNeedle || $needle === (string) $key) {
                return (string) $key;
            }
        }

        return null;
    }

    protected function rankIndex(?string $rank): int
    {
        $index = array_search($rank, $this->pricing('rank_order', []), true);

        return $index === false ? -1 : $index;
    }

    protected function evaluateAddonRules(array $data): array
    {
        return OrderAddonRules::evaluate([
            'serviceType' => $data['serviceType'] ?? null,
            'boostMode' => $this->boostModeLabel($data['boostMode'] ?? null),
            'currentDivision' => $data['currentFullRank'] ?? null,
            'targetDivision' => $data['targetFullRank'] ?? null,
            'selectedAddons' => $data['selectedAddons'] ?? [],
            'specificAgents' => $data['specificAgents'] ?? [],
            'oneTrickAgent' => $data['oneTrickAgent'] ?? [],
        ]);
    }

    protected function applyResolvedAddonSelections(array $data): array
    {
        $resolved = OrderAddonRules::stripInactiveSelections([
            'serviceType' => $data['serviceType'] ?? null,
            'boostMode' => $this->boostModeLabel($data['boostMode'] ?? null),
            'addons' => $data['selectedAddons'] ?? [],
            'specificAgents' => $data['specificAgents'] ?? [],
            'oneTrickAgent' => $data['oneTrickAgent'] ?? [],
        ]);

        $data['specificAgents'] = BoostingCatalog::normalizeSpecificAgents($resolved['specificAgents'] ?? []);
        $data['oneTrickAgent'] = BoostingCatalog::normalizeOneTrickAgent($resolved['oneTrickAgent'] ?? []);

        return $data;
    }

    protected function desiredDivisionDisplay(array $data): ?string
    {
        return match ($data['serviceKind']) {
            'ranked_wins' => ($data['numberOfWins'] ?? 0) > 0 ? "{$data['numberOfWins']} Wins" : null,
            'placement_matches' => ($data['numberOfPlacementGames'] ?? 0) > 0 ? "{$data['numberOfPlacementGames']} Placement Matches" : null,
            default => $data['targetFullRank'],
        };
    }

    protected function displayCurrentRr(array $data): ?string
    {
        return in_array($data['serviceKind'], ['rank_boost', 'radiant_boost'], true) && $data['currentRR'] !== null
            ? (string) $data['currentRR']
            : null;
    }

    protected function averageRrDisplay(array $data): ?string
    {
        return match ($data['serviceKind']) {
            'rank_boost', 'radiant_boost' => Arr::get($this->pricing('labels.avg_rr', []), $data['avgRRPerWin']),
            'ranked_wins' => ($data['numberOfWins'] ?? 0) > 0 ? "{$data['numberOfWins']} ranked wins" : null,
            'placement_matches' => ($data['numberOfPlacementGames'] ?? 0) > 0 ? "{$data['numberOfPlacementGames']} placement matches" : null,
            default => null,
        };
    }

    protected function boostModeLabel(?string $boostMode): ?string
    {
        return Arr::get($this->pricing('labels.boost_modes', []), $boostMode);
    }

    protected function regionMultiplier(?string $region): float
    {
        return (float) Arr::get($this->pricing('modifiers.region', []), $region, 1.00);
    }

    protected function platformMultiplier(?string $platform): float
    {
        return (float) Arr::get($this->pricing('modifiers.platform', []), $platform, 1.00);
    }

    protected function boostModeMultiplier(?string $boostMode): float
    {
        return (float) Arr::get($this->pricing('modifiers.boost_mode', []), $boostMode, 1.00);
    }

    protected function roundMoney(float $value): float
    {
        return round($value + 0.0000001, 2);
    }
}
