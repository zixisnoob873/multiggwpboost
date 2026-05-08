<?php

namespace App\Support\Pricing;

use App\Data\Pricing\PriceCalculationDto;
use App\Models\Game;
use App\Models\GameAddon;
use App\Models\GameService;
use App\Models\ServicePricingRule;
use App\Queries\Marketplace\GameRepository;
use App\Support\GameCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CatalogPricingEngine
{
    public function __construct(
        protected GameRepository $games,
        protected GameCatalog $gameCatalog,
    ) {}

    public function calculate(array|PriceCalculationDto $input, array $options = []): array
    {
        $payload = $input instanceof PriceCalculationDto ? $input->toArray() : $input;
        $gameSlug = $this->gameCatalog->resolveSlugFromPayload([
            ...$payload,
            'gameSlug' => $options['gameSlug'] ?? $payload['gameSlug'] ?? null,
        ]);
        $game = $this->games->findActiveBySlug($gameSlug);

        if (! $game instanceof Game) {
            return $this->failurePayload($gameSlug, null, [
                'gameSlug' => ['Select a valid game.'],
            ]);
        }

        $service = $this->resolveService($game, $payload, $options);

        if (! $service instanceof GameService) {
            return $this->failurePayload($gameSlug, null, [
                'serviceType' => ['Select a valid service type.'],
            ]);
        }

        $baseRule = $this->baseRule($service);
        $addonPriceOverrides = $this->normalizeAddonPriceOverrides($options['addonPriceOverrides'] ?? []);
        $normalized = $this->normalizeInput($game, $service, $payload);
        $validationErrors = $this->validate($game, $service, $normalized, $baseRule, $addonPriceOverrides);

        if ($validationErrors !== []) {
            return $this->failurePayload($gameSlug, $service, $validationErrors, $normalized);
        }

        [$basePrice, $rankPath, $baseErrors] = $this->calculateBasePrice($game, $service, $normalized, $baseRule);

        if ($baseErrors !== []) {
            return $this->failurePayload($gameSlug, $service, $baseErrors, $normalized);
        }

        [$addonBreakdown, $addonTotal, $subtotalAfterAddons] = $this->calculateAddons($service, $normalized['selectedAddons'], $basePrice, $addonPriceOverrides);
        $finalPrice = $this->roundMoney($subtotalAfterAddons);

        return array_merge($this->basePayload($game, $service, $normalized), [
            'basePrice' => $this->roundMoney($basePrice),
            'rankPath' => $rankPath,
            'addonBreakdown' => $addonBreakdown,
            'subtotalBeforeModifiers' => $this->roundMoney($basePrice),
            'subtotalAfterRR' => $this->roundMoney($basePrice),
            'subtotalAfterAddons' => $this->roundMoney($subtotalAfterAddons),
            'subtotalAfterGlobalModifiers' => $finalPrice,
            'finalPrice' => $finalPrice,
            'validationErrors' => [],
            'pricing' => [
                'base' => $this->roundMoney($basePrice),
                'basePrice' => $this->roundMoney($basePrice),
                'subtotal' => $finalPrice,
                'subtotalBeforeModifiers' => $this->roundMoney($basePrice),
                'subtotalAfterRR' => $this->roundMoney($basePrice),
                'subtotalAfterAddons' => $this->roundMoney($subtotalAfterAddons),
                'subtotalAfterGlobalModifiers' => $finalPrice,
                'addons' => $this->roundMoney($addonTotal),
                'fee' => 0,
                'tax' => 0,
                'total' => $finalPrice,
                'finalPrice' => $finalPrice,
                'currency' => 'USD',
            ],
            'modifiers' => [
                'queueType' => [
                    'code' => $normalized['queueType'],
                    'label' => $this->queueLabel($normalized['queueType']),
                ],
            ],
        ]);
    }

    protected function resolveService(Game $game, array $payload, array $options): ?GameService
    {
        $services = $game->relationLoaded('services')
            ? $game->services
            : $game->services()->with(['game', 'addons.pricingRules', 'pricingRules'])->orderBy('sort_order')->orderBy('id')->get();
        $services = $services->where('status', Game::STATUS_PUBLISHED)->values();
        $slug = Str::slug((string) ($options['serviceSlug'] ?? $payload['serviceSlug'] ?? $payload['service_slug'] ?? ''));

        if ($slug !== '') {
            $service = $services->first(fn (GameService $candidate): bool => (string) $candidate->slug === $slug);

            if ($service instanceof GameService) {
                return $service;
            }
        }

        $needle = $this->normalizeComparable(
            $payload['serviceType']
            ?? $payload['orderType']
            ?? $payload['service']
            ?? null
        );

        if ($needle === '') {
            return null;
        }

        return $services->first(function (GameService $candidate) use ($needle): bool {
            return in_array($needle, $this->serviceComparableValues($candidate), true);
        });
    }

    protected function serviceComparableValues(GameService $service): array
    {
        return collect([
            $service->name,
            $service->slug,
            $service->kind,
            ...((array) data_get($service->metadata, 'aliases', [])),
        ])
            ->map(fn (mixed $value): string => $this->normalizeComparable($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeInput(Game $game, GameService $service, array $payload): array
    {
        $queueType = $this->normalizeQueueType(
            $payload['queueType']
            ?? $payload['queue_type']
            ?? $payload['boostMode']
            ?? $payload['accountType']
            ?? $payload['playType']
            ?? null
        );
        [$selectedAddons, $addonValidationErrors] = $this->normalizeAddons($service, $payload, $queueType);
        $currentRank = $this->canonicalRank($game, $payload['currentDivision'] ?? $payload['currentRank'] ?? null);
        $targetRank = $this->canonicalRank(
            $game,
            $payload['targetDivision']
            ?? $payload['desiredDivision']
            ?? $payload['desiredRank']
            ?? $payload['targetRank']
            ?? null
        );

        return [
            'serviceType' => $service->name,
            'serviceSlug' => $service->slug,
            'serviceKind' => $service->kind,
            'selectedOptions' => (array) ($payload['selectedOptions'] ?? $payload['selected_options'] ?? []),
            'currentFullRank' => $currentRank,
            'targetFullRank' => $targetRank,
            'currentRank' => $this->splitRank($currentRank)['tier'],
            'targetRank' => $this->splitRank($targetRank)['tier'],
            'currentLevel' => $this->nullableInt($payload['currentLevel'] ?? $payload['current_level'] ?? null),
            'desiredLevel' => $this->nullableInt($payload['desiredLevel'] ?? $payload['desired_level'] ?? null),
            'queueType' => $queueType,
            'selectedAddons' => $selectedAddons,
            'addonValidationErrors' => $addonValidationErrors,
        ];
    }

    protected function validate(Game $game, GameService $service, array $normalized, ?ServicePricingRule $baseRule, array $addonPriceOverrides = []): array
    {
        $errors = $normalized['addonValidationErrors'] ?? [];
        $rankLabels = $this->rankLabels($game);

        if ($rankLabels->isNotEmpty() && $this->usesRankPath($service, $baseRule)) {
            if (! $normalized['currentFullRank']) {
                $errors['currentRank'][] = 'Select a valid current rank.';
            }

            if (! $normalized['targetFullRank']) {
                $errors['targetRank'][] = 'Select a valid target rank.';
            }

            if (
                $normalized['currentFullRank']
                && $normalized['targetFullRank']
                && $this->usesRankPath($service, $baseRule)
                && $this->rankIndex($game, $normalized['targetFullRank']) <= $this->rankIndex($game, $normalized['currentFullRank'])
            ) {
                $errors['targetRank'][] = 'Select a target rank above the current rank.';
            }
        }

        $addonsByLabel = $this->serviceAddons($service)->keyBy(fn (GameAddon $addon): string => $addon->label);

        foreach ($normalized['selectedAddons'] as $label) {
            $addon = $addonsByLabel->get($label);

            if (! $addon instanceof GameAddon) {
                $errors['selectedAddons'][] = "{$label} is not available for this service.";
                continue;
            }

            $pricing = $this->addonPricing($service, $addon, $addonPriceOverrides);

            if (! in_array($pricing['type'], [
                'free',
                ServicePricingRule::PRICING_FIXED,
                ServicePricingRule::PRICING_PERCENTAGE,
                ServicePricingRule::PRICING_MULTIPLIER,
            ], true)) {
                $errors['selectedAddons'][] = "{$label} has an invalid pricing rule.";
            }
        }

        return collect($errors)
            ->map(fn (array $messages): array => array_values(array_unique($messages)))
            ->filter()
            ->all();
    }

    protected function calculateBasePrice(Game $game, GameService $service, array $normalized, ?ServicePricingRule $baseRule): array
    {
        if (! $this->usesRankPath($service, $baseRule)) {
            return [$this->basePrice($service, $baseRule), [], []];
        }

        $rankLabels = $this->rankLabels($game);
        $currentIndex = $this->rankIndex($game, $normalized['currentFullRank']);
        $targetIndex = $this->rankIndex($game, $normalized['targetFullRank']);

        if ($currentIndex < 0 || $targetIndex < 0 || $targetIndex <= $currentIndex) {
            return [0.0, [], [
                'targetRank' => ['Select a target rank above the current rank.'],
            ]];
        }

        $rankPath = [];
        $basePrice = 0.0;

        for ($index = $currentIndex; $index < $targetIndex; $index++) {
            $fromRank = (string) $rankLabels->get($index);
            $toRank = (string) $rankLabels->get($index + 1);
            $stepPrice = $this->rankStepPrice($service, $baseRule, $fromRank, $toRank);

            if ($stepPrice === null) {
                return [0.0, $rankPath, [
                    'targetRank' => ["Pricing is not configured for {$fromRank} to {$toRank}."],
                ]];
            }

            $amount = $this->roundMoney($stepPrice);
            $rankPath[] = [
                'from' => $fromRank,
                'to' => $toRank,
                'amount' => $amount,
            ];
            $basePrice += $amount;
        }

        return [$this->roundMoney($basePrice), $rankPath, []];
    }

    protected function baseRule(GameService $service): ?ServicePricingRule
    {
        return $this->activeRules($service->pricingRules)->first(
            fn (ServicePricingRule $candidate): bool => $candidate->scope === ServicePricingRule::SCOPE_BASE
        );
    }

    protected function usesRankPath(GameService $service, ?ServicePricingRule $baseRule): bool
    {
        $calculatorKey = $this->normalizeComparable($baseRule?->calculator_key);
        $serviceKind = $this->normalizeComparable($service->kind);
        $tiers = (array) ($baseRule?->tiers ?? []);

        return in_array($calculatorKey, ['rank-to-rank', 'rank-path', 'rank-boost'], true)
            || in_array($serviceKind, ['rank-boost', 'rank-to-rank', 'boosting'], true)
            || data_get($tiers, 'steps') !== null
            || data_get($tiers, 'rank_prices') !== null
            || data_get($tiers, 'rankPrices') !== null;
    }

    protected function rankStepPrice(GameService $service, ?ServicePricingRule $baseRule, string $fromRank, string $toRank): ?float
    {
        $tiers = (array) ($baseRule?->tiers ?? []);
        $stepKey = "{$fromRank}->{$toRank}";
        $normalizedStepKey = $this->normalizeComparable($stepKey);
        $steps = (array) data_get($tiers, 'steps', []);

        foreach ($steps as $key => $amount) {
            if ($this->normalizeComparable($key) === $normalizedStepKey && is_numeric($amount)) {
                return max(0, (float) $amount);
            }
        }

        $rankPrices = (array) (data_get($tiers, 'rank_prices') ?? data_get($tiers, 'rankPrices', []));
        $normalizedFromRank = $this->normalizeComparable($fromRank);

        foreach ($rankPrices as $key => $amount) {
            if ($this->normalizeComparable($key) === $normalizedFromRank && is_numeric($amount)) {
                return max(0, (float) $amount);
            }
        }

        if ($baseRule instanceof ServicePricingRule && $baseRule->amount !== null) {
            return max(0, (float) $baseRule->amount);
        }

        $fallback = $this->basePrice($service, $baseRule);

        return $fallback > 0 ? $fallback : null;
    }

    protected function rankIndex(Game $game, ?string $rank): int
    {
        $index = $this->rankLabels($game)->search(
            fn (string $label): bool => $this->normalizeComparable($label) === $this->normalizeComparable($rank)
        );

        return $index === false ? -1 : (int) $index;
    }

    protected function basePayload(Game $game, GameService $service, array $normalized): array
    {
        return [
            'gameSlug' => $game->slug,
            'game' => $game->name,
            'serviceSlug' => $service->slug,
            'serviceType' => $service->name,
            'orderType' => $service->name,
            'serviceKind' => $service->kind,
            'currentRank' => $normalized['currentRank'],
            'targetRank' => $normalized['targetRank'],
            'currentDivision' => $normalized['currentFullRank'],
            'desiredDivision' => $normalized['targetFullRank'],
            'currentLevel' => $normalized['currentLevel'],
            'desiredLevel' => $normalized['desiredLevel'],
            'queueType' => $normalized['queueType'],
            'boostMode' => $normalized['queueType'],
            'accountType' => $this->queueLabel($normalized['queueType']),
            'selectedOptions' => $normalized['selectedOptions'],
            'requestedAddons' => $normalized['selectedAddons'],
            'selectedAddons' => $normalized['selectedAddons'],
            'addons' => $normalized['selectedAddons'],
            'disabledAddons' => [],
            'disabledAddonReasons' => [],
            'specificAgents' => [],
            'oneTrickAgent' => [],
            'pricingConfig' => [
                'gameSlug' => $game->slug,
                'serviceSlug' => $service->slug,
                'version' => 0,
                'checksum' => $this->catalogChecksum($service),
                'source' => 'catalog',
                'updatedAt' => optional($service->updated_at)->toIso8601String(),
            ],
            'updatedAt' => now()->toIso8601String(),
        ];
    }

    protected function failurePayload(string $gameSlug, ?GameService $service, array $errors, array $normalized = []): array
    {
        $gameName = $this->gameCatalog->gameName($gameSlug);
        $serviceName = $service?->name ?? ($normalized['serviceType'] ?? null);

        return [
            'gameSlug' => $gameSlug,
            'game' => $gameName,
            'serviceSlug' => $service?->slug,
            'serviceType' => $serviceName,
            'orderType' => $serviceName,
            'serviceKind' => $service?->kind,
            'currentRank' => $normalized['currentRank'] ?? null,
            'targetRank' => $normalized['targetRank'] ?? null,
            'currentDivision' => $normalized['currentFullRank'] ?? null,
            'desiredDivision' => $normalized['targetFullRank'] ?? null,
            'currentLevel' => $normalized['currentLevel'] ?? null,
            'desiredLevel' => $normalized['desiredLevel'] ?? null,
            'queueType' => $normalized['queueType'] ?? 'normal',
            'boostMode' => $normalized['queueType'] ?? 'normal',
            'accountType' => $this->queueLabel($normalized['queueType'] ?? 'normal'),
            'selectedOptions' => $normalized['selectedOptions'] ?? [],
            'requestedAddons' => $normalized['selectedAddons'] ?? [],
            'selectedAddons' => $normalized['selectedAddons'] ?? [],
            'addons' => [],
            'disabledAddons' => [],
            'disabledAddonReasons' => [],
            'basePrice' => 0,
            'rankPath' => [],
            'addonBreakdown' => [],
            'subtotalBeforeModifiers' => 0,
            'subtotalAfterRR' => 0,
            'subtotalAfterAddons' => 0,
            'subtotalAfterGlobalModifiers' => 0,
            'finalPrice' => 0,
            'validationErrors' => $errors,
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
            'modifiers' => [],
        ];
    }

    protected function basePrice(GameService $service, ?ServicePricingRule $baseRule = null): float
    {
        if ($baseRule instanceof ServicePricingRule && $baseRule->amount !== null) {
            return max(0, (float) $baseRule->amount);
        }

        return match ((string) $service->kind) {
            'coaching' => 19.00,
            'weapon_leveling' => 15.00,
            'power_leveling' => 24.00,
            'battle_pass_completion' => 29.00,
            'challenges' => 12.00,
            'farming' => 18.00,
            default => 9.00,
        };
    }

    protected function calculateAddons(GameService $service, array $labels, float $basePrice, array $addonPriceOverrides = []): array
    {
        $addonsByLabel = $this->serviceAddons($service)->keyBy(fn (GameAddon $addon): string => $addon->label);
        $breakdown = [];
        $runningSubtotal = max(0, $basePrice);
        $multipliers = [];

        foreach ($labels as $label) {
            $addon = $addonsByLabel->get($label);

            if (! $addon instanceof GameAddon) {
                continue;
            }

            $pricing = $this->addonPricing($service, $addon, $addonPriceOverrides);
            $type = $pricing['type'];
            $value = $pricing['value'];

            if ($type === ServicePricingRule::PRICING_MULTIPLIER) {
                $multipliers[] = [$label, $type, $value, $pricing['source']];
                continue;
            }

            $amount = match ($type) {
                ServicePricingRule::PRICING_FIXED => max(0, $value),
                ServicePricingRule::PRICING_PERCENTAGE => max(0, $basePrice) * ($value > 1 ? $value / 100 : $value),
                default => 0.0,
            };

            $breakdown[] = [
                'label' => $label,
                'type' => $type,
                'amount' => $this->roundMoney($amount),
                'value' => $value,
                'pricingSource' => $pricing['source'],
            ];
            $runningSubtotal += $amount;
        }

        foreach ($multipliers as [$label, $type, $value, $source]) {
            $multiplier = max(0, $value);
            $before = $runningSubtotal;
            $runningSubtotal *= $multiplier;
            $amount = $runningSubtotal - $before;

            $breakdown[] = [
                'label' => $label,
                'type' => $type,
                'amount' => $this->roundMoney($amount),
                'value' => $value,
                'pricingSource' => $source,
            ];
        }

        return [$breakdown, $this->roundMoney($runningSubtotal - $basePrice), $this->roundMoney($runningSubtotal)];
    }

    protected function addonPricing(GameService $service, GameAddon $addon, array $addonPriceOverrides = []): array
    {
        $override = $addonPriceOverrides[$this->normalizeComparable($addon->label)]
            ?? $addonPriceOverrides[$this->normalizeComparable($addon->slug)]
            ?? null;

        if (is_array($override)) {
            return [
                'type' => (string) ($override['type'] ?? 'free'),
                'value' => (float) ($override['value'] ?? 0),
                'source' => 'promo_override',
            ];
        }

        $rule = $this->activeRules($addon->pricingRules)
            ->filter(fn (ServicePricingRule $candidate): bool => $candidate->service_id === null || (int) $candidate->service_id === (int) $service->id)
            ->sortBy(function (ServicePricingRule $candidate): string {
                $specificity = $candidate->service_id === null ? 1 : 0;

                return sprintf('%01d-%08d-%08d', $specificity, (int) $candidate->sort_order, (int) $candidate->id);
            })
            ->first();

        if ($rule instanceof ServicePricingRule) {
            return [
                'type' => (string) $rule->pricing_type,
                'value' => (float) ($rule->amount ?? 0),
                'source' => $rule->service_id === null ? 'catalog' : 'service_rule',
            ];
        }

        return [
            'type' => (string) ($addon->pricing_type ?: 'free'),
            'value' => (float) ($addon->pricing_value ?? data_get($addon->pricing_rule, 'value', 0)),
            'source' => 'catalog',
        ];
    }

    protected function normalizeAddons(GameService $service, array $payload, string $queueType): array
    {
        $requested = collect($payload['selectedAddons'] ?? $payload['addons'] ?? []);

        if (($payload['duoQueue'] ?? $payload['duo_queue'] ?? false) || $queueType === 'self_play') {
            $requested->push('Duo Queue');
        }

        if ($payload['streamGames'] ?? $payload['stream_games'] ?? false) {
            $requested->push('Streamed Games');
        }

        if ($payload['expressDelivery'] ?? $payload['express_delivery'] ?? false) {
            $requested->push('Express Delivery');
        }

        $lookup = $this->addonLookup($service);
        $gameLookup = $this->gameAddonLookup($service);
        $labels = [];
        $errors = [];

        foreach ($requested as $value) {
            $normalized = $this->normalizeComparable($value);

            if ($normalized === '') {
                continue;
            }

            $label = $lookup[$normalized] ?? null;

            if (is_string($label) && $label !== '') {
                $labels[] = $label;
                continue;
            }

            $gameLabel = $gameLookup[$normalized] ?? null;

            if (is_string($gameLabel) && $gameLabel !== '') {
                $errors['selectedAddons'][] = "{$gameLabel} is not available for this service.";
                continue;
            }

            $errors['selectedAddons'][] = 'Select a valid addon for this service.';
        }

        return [
            array_values(array_unique($labels)),
            $errors,
        ];
    }

    protected function addonLookup(GameService $service): array
    {
        $lookup = [];

        foreach ($this->serviceAddons($service) as $addon) {
            foreach ([
                $addon->slug,
                $addon->label,
                ...((array) data_get($addon->metadata, 'aliases', [])),
            ] as $candidate) {
                $normalized = $this->normalizeComparable($candidate);

                if ($normalized !== '') {
                    $lookup[$normalized] = $addon->label;
                }
            }
        }

        foreach ([
            'duo' => 'Duo Queue',
            'duo self play' => 'Duo Queue',
            'self play' => 'Duo Queue',
            'streaming' => 'Streamed Games',
            'streamed games' => 'Streamed Games',
            'live streaming' => 'Streamed Games',
            'express order' => 'Express Delivery',
            'express' => 'Express Delivery',
            'priority' => 'Priority Order',
            'win streak' => 'Win Streak Guarantee',
        ] as $alias => $label) {
            if ($this->serviceAddons($service)->contains(fn (GameAddon $addon): bool => $addon->label === $label)) {
                $lookup[$this->normalizeComparable($alias)] = $label;
            }
        }

        return $lookup;
    }

    protected function gameAddonLookup(GameService $service): array
    {
        $game = $service->relationLoaded('game') ? $service->game : $service->game()->first();
        $addons = $game instanceof Game
            ? $game->addons()->where('status', Game::STATUS_PUBLISHED)->get()
            : collect();
        $lookup = [];

        foreach ($addons as $addon) {
            foreach ([
                $addon->slug,
                $addon->label,
                ...((array) data_get($addon->metadata, 'aliases', [])),
            ] as $candidate) {
                $normalized = $this->normalizeComparable($candidate);

                if ($normalized !== '') {
                    $lookup[$normalized] = $addon->label;
                }
            }
        }

        return $lookup;
    }

    protected function serviceAddons(GameService $service): Collection
    {
        $addons = $service->relationLoaded('addons') ? $service->addons : $service->addons()->with('pricingRules')->get();

        return $addons
            ->filter(function (GameAddon $addon): bool {
                $pivotStatus = $addon->pivot?->status ?? Game::STATUS_PUBLISHED;

                return $addon->status === Game::STATUS_PUBLISHED && $pivotStatus === Game::STATUS_PUBLISHED;
            })
            ->values();
    }

    protected function activeRules(Collection $rules): Collection
    {
        return $rules
            ->filter(function (ServicePricingRule $rule): bool {
                if ($rule->status !== ServicePricingRule::STATUS_PUBLISHED) {
                    return false;
                }

                if ($rule->starts_at && $rule->starts_at->isFuture()) {
                    return false;
                }

                return ! ($rule->ends_at && $rule->ends_at->isPast());
            })
            ->sortBy(fn (ServicePricingRule $rule): string => sprintf('%08d-%08d', (int) $rule->sort_order, (int) $rule->id))
            ->values();
    }

    protected function normalizeAddonPriceOverrides(mixed $overrides): array
    {
        if (! is_array($overrides)) {
            return [];
        }

        $normalized = [];

        foreach ($overrides as $label => $override) {
            if (! is_array($override)) {
                continue;
            }

            $key = $this->normalizeComparable($label);
            $type = (string) ($override['type'] ?? '');
            $value = $override['value'] ?? null;

            if ($key === '' || $type === '' || ! is_numeric($value)) {
                continue;
            }

            $normalized[$key] = [
                'type' => $type,
                'value' => (float) $value,
            ];
        }

        return $normalized;
    }

    protected function rankLabels(Game $game): Collection
    {
        $ranks = $game->relationLoaded('ranks') ? $game->ranks : $game->ranks()->orderBy('sort_order')->get();

        return $ranks->pluck('label')->filter()->values();
    }

    protected function catalogChecksum(GameService $service): string
    {
        $addons = $this->serviceAddons($service)->map(function (GameAddon $addon): array {
            return [
                'id' => $addon->id,
                'label' => $addon->label,
                'pricing_type' => $addon->pricing_type,
                'pricing_value' => (string) $addon->pricing_value,
                'pricing_rules' => $this->activeRules($addon->pricingRules)->map(fn (ServicePricingRule $rule): array => [
                    'id' => $rule->id,
                    'service_id' => $rule->service_id,
                    'pricing_type' => $rule->pricing_type,
                    'amount' => (string) $rule->amount,
                    'updated_at' => optional($rule->updated_at)->toIso8601String(),
                ])->values()->all(),
            ];
        })->values()->all();

        $payload = [
            'service_id' => $service->id,
            'service_updated_at' => optional($service->updated_at)->toIso8601String(),
            'rules' => $this->activeRules($service->pricingRules)->map(fn (ServicePricingRule $rule): array => [
                'id' => $rule->id,
                'calculator_key' => $rule->calculator_key,
                'pricing_type' => $rule->pricing_type,
                'amount' => (string) $rule->amount,
                'tiers' => $rule->tiers,
                'updated_at' => optional($rule->updated_at)->toIso8601String(),
            ])->values()->all(),
            'addons' => $addons,
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    protected function canonicalRank(Game $game, mixed $value): ?string
    {
        $needle = $this->normalizeComparable($value);

        if ($needle === '') {
            return null;
        }

        return $this->rankLabels($game)
            ->first(fn (string $label): bool => $this->normalizeComparable($label) === $needle);
    }

    protected function splitRank(?string $rank): array
    {
        if (! $rank) {
            return ['tier' => null];
        }

        $parts = preg_split('/\s+/', $rank) ?: [];

        return ['tier' => $parts[0] ?? $rank];
    }

    protected function normalizeQueueType(mixed $value): string
    {
        $normalized = Str::of((string) $value)
            ->lower()
            ->replace(['-', '/', '\\'], '_')
            ->replace(' ', '_')
            ->replaceMatches('/_+/', '_')
            ->trim('_')
            ->value();

        return in_array($normalized, ['duo', 'duo_queue', 'self_play', 'duo_self_play', 'self_play_duo'], true)
            ? 'self_play'
            : 'normal';
    }

    protected function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    protected function queueLabel(string $queueType): string
    {
        return $queueType === 'self_play' ? 'Duo Queue' : 'Account Shared';
    }

    protected function normalizeComparable(mixed $value): string
    {
        if (! is_scalar($value) && ! $value instanceof \Stringable) {
            return '';
        }

        return Str::of((string) $value)
            ->lower()
            ->replace('_', '-')
            ->replaceMatches('/[()+$%]/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();
    }

    protected function roundMoney(float $value): float
    {
        return round($value + 0.0000001, 2);
    }
}
