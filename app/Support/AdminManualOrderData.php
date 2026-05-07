<?php

namespace App\Support;

class AdminManualOrderData
{
    public static function selectionValidationErrors(array $rawSelections, array $selectedAddons = []): array
    {
        $errors = [];
        $normalizedAddons = BoostingCatalog::normalizeAddons($selectedAddons);

        foreach (BoostingCatalog::agentSelectionAddons() as $key => $definition) {
            $selection = AgentSelectionValidator::inspect(
                $key,
                $rawSelections[$key] ?? $rawSelections[$definition['input_name'] ?? ''] ?? []
            );
            $messages = AgentSelectionValidator::messagesFromSelection($key, $selection, false);
            $hasAddon = in_array($definition['label'], $normalizedAddons, true);

            if ($hasAddon) {
                $messages = array_merge($messages, AgentSelectionValidator::messagesFromSelection($key, $selection, true));
            } elseif (($selection['uuids'] ?? []) !== []) {
                $messages[] = $definition['addon_required_message'];
            }

            if ($messages === []) {
                continue;
            }

            $errors[$definition['input_name']] = array_values(array_unique(array_filter($messages)));
        }

        return $errors;
    }

    public static function orderPayload(array $data): array
    {
        $gameSlug = BoostingCatalog::normalizeGameSlug($data['game'] ?? $data['gameSlug'] ?? null);
        $serviceType = BoostingCatalog::normalizeServiceType($data['product'] ?? null)
            ?? self::stringValue($data['product'] ?? 'Rank Boosting')
            ?? 'Rank Boosting';
        $desiredDivision = $serviceType === 'Radiant Boost'
            ? 'Radiant'
            : self::rankValue($data['desired_division'] ?? $data['desiredDivision'] ?? null);
        $averageRr = self::stringValue($data['average_rr'] ?? $data['averageRR'] ?? null);
        $boostMode = BoostingCatalog::normalizeBoostModeLabel($data['account_type'] ?? $data['accountType'] ?? null)
            ?? self::stringValue($data['account_type'] ?? $data['accountType'] ?? null);
        $addons = BoostingCatalog::normalizeAddons($data['addons'] ?? []);
        $specificAgents = BoostingCatalog::normalizeSpecificAgents($data['specific_agents'] ?? $data['specificAgents'] ?? []);
        $oneTrickAgent = BoostingCatalog::normalizeOneTrickAgent($data['one_trick_agent'] ?? $data['oneTrickAgent'] ?? []);

        return array_filter([
            'gameSlug' => $gameSlug,
            'game' => BoostingCatalog::gameName($gameSlug),
            'orderType' => $serviceType,
            'serviceType' => $serviceType,
            'currentDivision' => self::rankValue($data['current_division'] ?? $data['currentDivision'] ?? null),
            'desiredDivision' => $desiredDivision,
            'targetDivision' => $desiredDivision,
            'currentRR' => self::integerValue($data['current_rr'] ?? $data['currentRR'] ?? null),
            'averageRR' => $averageRr,
            'avgRRPerWin' => $averageRr,
            'region' => self::stringValue($data['region'] ?? null),
            'platform' => self::stringValue($data['platform'] ?? null),
            'accountType' => $boostMode,
            'boostMode' => $boostMode,
            'addons' => $addons,
            'selectedAddons' => $addons,
            'specificAgents' => $specificAgents,
            'oneTrickAgent' => $oneTrickAgent,
            'numberOfWins' => self::integerValue($data['number_of_wins'] ?? $data['numberOfWins'] ?? null),
            'numberOfPlacementGames' => self::integerValue($data['number_of_placement_games'] ?? $data['numberOfPlacementGames'] ?? null),
        ], fn ($value) => ! ($value === null || $value === ''));
    }

    public static function payloadFromDetails(array $details, ?string $product = null): array
    {
        return self::orderPayload([
            'product' => $product
                ?? self::detailValue($details, ['service', 'order.orderType', 'order.serviceType'])
                ?? 'Rank Boosting',
            'game' => self::detailValue($details, ['gameSlug', 'game', 'order.gameSlug', 'order.game']),
            'current_division' => self::detailValue($details, [
                'from',
                'currentDivision',
                'order.currentDivision',
                'order.currentRank',
            ]),
            'desired_division' => self::detailValue($details, [
                'to',
                'desiredDivision',
                'order.desiredDivision',
                'order.targetDivision',
                'order.targetRank',
            ]),
            'current_rr' => self::detailValue($details, ['currentRR', 'order.currentRR']),
            'average_rr' => self::detailValue($details, ['averageRR', 'order.averageRR', 'order.avgRRPerWin']),
            'region' => self::detailValue($details, ['region', 'order.region']),
            'platform' => self::detailValue($details, ['platform', 'order.platform']),
            'account_type' => self::detailValue($details, ['accountType', 'order.accountType', 'order.boostMode']),
            'addons' => self::detailValue($details, ['addons', 'order.addons', 'order.selectedAddons'], []),
            'specific_agents' => self::detailValue($details, [
                'specificAgents',
                'specific_agents',
                'order.specificAgents',
                'order.specific_agents',
            ], []),
            'one_trick_agent' => self::detailValue($details, [
                'oneTrickAgent',
                'one_trick_agent',
                'order.oneTrickAgent',
                'order.one_trick_agent',
            ], []),
            'number_of_wins' => self::detailValue($details, ['numberOfWins', 'order.numberOfWins']),
            'number_of_placement_games' => self::detailValue($details, ['numberOfPlacementGames', 'order.numberOfPlacementGames']),
        ]);
    }

    public static function syncDetails(array $details, array $payload, array $attributes = []): array
    {
        $service = $payload['orderType'] ?? $payload['serviceType'] ?? null;
        $existingOrder = is_array($details['order'] ?? null) ? $details['order'] : [];
        $details['service'] = $service;
        $details['from'] = $payload['currentDivision'] ?? null;
        $details['to'] = $payload['desiredDivision'] ?? null;
        $details['currentRR'] = $payload['currentRR'] ?? null;
        $details['averageRR'] = $payload['averageRR'] ?? null;
        $details['region'] = $payload['region'] ?? null;
        $details['platform'] = $payload['platform'] ?? null;
        $details['accountType'] = $payload['accountType'] ?? null;
        $details['addons'] = BoostingCatalog::normalizeAddons($payload['addons'] ?? $payload['selectedAddons'] ?? []);
        $details['specificAgents'] = BoostingCatalog::normalizeSpecificAgents($payload['specificAgents'] ?? []);
        $details['oneTrickAgent'] = BoostingCatalog::normalizeOneTrickAgent($payload['oneTrickAgent'] ?? []);
        $details['numberOfWins'] = $payload['numberOfWins'] ?? null;
        $details['numberOfPlacementGames'] = $payload['numberOfPlacementGames'] ?? null;
        $details['order'] = array_merge($existingOrder, $payload, is_array($attributes['order'] ?? null) ? $attributes['order'] : []);

        if (array_key_exists('source', $attributes)) {
            $details['source'] = $attributes['source'];
        }

        if (array_key_exists('orderCategory', $attributes)) {
            $details['orderCategory'] = $attributes['orderCategory'];
        }

        $adminOverride = is_array($details['adminOverride'] ?? null) ? $details['adminOverride'] : [];
        if (is_array($attributes['adminOverride'] ?? null)) {
            $adminOverride = array_merge($adminOverride, $attributes['adminOverride']);
        }
        if ($adminOverride !== []) {
            $details['adminOverride'] = $adminOverride;
        }

        unset($details['specific_agents'], $details['one_trick_agent']);
        unset($details['order']['specific_agents'], $details['order']['one_trick_agent']);

        return $details;
    }

    public static function manualPriceProvided(mixed $value): bool
    {
        return ! ($value === null || (is_string($value) && trim($value) === ''));
    }

    public static function manualPriceCents(mixed $value): ?int
    {
        if (! self::manualPriceProvided($value) || ! is_numeric($value)) {
            return null;
        }

        return (int) round(((float) $value) * 100);
    }

    protected static function detailValue(array $details, array $keys, mixed $fallback = null): mixed
    {
        foreach ($keys as $key) {
            $value = data_get($details, $key, new \stdClass);

            if (! $value instanceof \stdClass) {
                return $value;
            }
        }

        return $fallback;
    }

    protected static function stringValue(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }

    protected static function integerValue(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    protected static function rankValue(mixed $value): ?string
    {
        $canonical = BoostingCatalog::canonicalRankLabel($value);

        return $canonical ?? self::stringValue($value);
    }
}
