<?php

namespace App\Data\Pricing;

final readonly class PricingRequest
{
    public function __construct(
        public ?string $gameSlug,
        public ?string $serviceSlug,
        public ?string $serviceType,
        public ?string $orderType,
        public ?string $currentRank,
        public ?string $currentDivision,
        public ?string $desiredRank,
        public ?string $desiredDivision,
        public ?string $targetRank,
        public ?string $targetDivision,
        public ?int $currentRR,
        public ?string $avgRRPerWin,
        public ?string $averageRR,
        public ?string $region,
        public ?string $platform,
        public ?string $boostMode,
        public ?string $queueType,
        public ?string $accountType,
        public ?string $playType,
        public ?int $currentLevel,
        public ?int $desiredLevel,
        public array $selectedOptions,
        public ?bool $duoQueue,
        public ?bool $streamGames,
        public ?bool $expressDelivery,
        public array $addons,
        public array $selectedAddons,
        public array $specificAgents,
        public array $oneTrickAgent,
        public ?int $wins,
        public ?int $numberOfWins,
        public ?int $placementGames,
        public ?int $numberOfPlacementGames,
        public ?float $clientTotal,
        public array $clientSubmittedTotals,
        public array $rawPayload,
    ) {}

    public static function fromArray(array $payload): self
    {
        $clientSubmittedTotals = self::clientSubmittedTotals($payload);

        return new self(
            gameSlug: self::nullableString($payload['gameSlug'] ?? $payload['game_slug'] ?? data_get($payload, 'game.slug') ?? (is_scalar($payload['game'] ?? null) ? $payload['game'] : null)),
            serviceSlug: self::nullableString($payload['serviceSlug'] ?? $payload['service_slug'] ?? null),
            serviceType: self::nullableString($payload['serviceType'] ?? null),
            orderType: self::nullableString($payload['orderType'] ?? null),
            currentRank: self::nullableString($payload['currentRank'] ?? $payload['current_rank'] ?? null),
            currentDivision: self::nullableString($payload['currentDivision'] ?? $payload['current_division'] ?? null),
            desiredRank: self::nullableString($payload['desiredRank'] ?? $payload['desired_rank'] ?? null),
            desiredDivision: self::nullableString($payload['desiredDivision'] ?? $payload['desired_division'] ?? null),
            targetRank: self::nullableString($payload['targetRank'] ?? $payload['target_rank'] ?? null),
            targetDivision: self::nullableString($payload['targetDivision'] ?? $payload['target_division'] ?? null),
            currentRR: self::nullableInt($payload['currentRR'] ?? $payload['current_rr'] ?? null),
            avgRRPerWin: self::nullableString($payload['avgRRPerWin'] ?? $payload['avg_rr_per_win'] ?? null),
            averageRR: self::nullableString($payload['averageRR'] ?? $payload['average_rr'] ?? null),
            region: self::nullableString($payload['region'] ?? null),
            platform: self::nullableString($payload['platform'] ?? null),
            boostMode: self::nullableString($payload['boostMode'] ?? $payload['boost_mode'] ?? null),
            queueType: self::nullableString($payload['queueType'] ?? $payload['queue_type'] ?? null),
            accountType: self::nullableString($payload['accountType'] ?? $payload['account_type'] ?? null),
            playType: self::nullableString($payload['playType'] ?? $payload['play_type'] ?? null),
            currentLevel: self::nullableInt($payload['currentLevel'] ?? $payload['current_level'] ?? null),
            desiredLevel: self::nullableInt($payload['desiredLevel'] ?? $payload['desired_level'] ?? null),
            selectedOptions: self::structuredArray($payload['selectedOptions'] ?? $payload['selected_options'] ?? []),
            duoQueue: self::nullableBool($payload['duoQueue'] ?? $payload['duo_queue'] ?? null),
            streamGames: self::nullableBool($payload['streamGames'] ?? $payload['stream_games'] ?? null),
            expressDelivery: self::nullableBool($payload['expressDelivery'] ?? $payload['express_delivery'] ?? null),
            addons: self::stringArray($payload['addons'] ?? []),
            selectedAddons: self::stringArray($payload['selectedAddons'] ?? $payload['selected_addons'] ?? []),
            specificAgents: self::stringArray($payload['specificAgents'] ?? $payload['specific_agents'] ?? []),
            oneTrickAgent: self::stringArray($payload['oneTrickAgent'] ?? $payload['one_trick_agent'] ?? []),
            wins: self::nullableInt($payload['wins'] ?? null),
            numberOfWins: self::nullableInt($payload['numberOfWins'] ?? $payload['number_of_wins'] ?? null),
            placementGames: self::nullableInt($payload['placementGames'] ?? $payload['placement_games'] ?? null),
            numberOfPlacementGames: self::nullableInt($payload['numberOfPlacementGames'] ?? $payload['number_of_placement_games'] ?? $payload['games'] ?? null),
            clientTotal: self::nullableFloat($payload['clientTotal'] ?? $payload['client_total'] ?? null),
            clientSubmittedTotals: $clientSubmittedTotals,
            rawPayload: $payload,
        );
    }

    public static function fromDto(PriceCalculationDto $dto): self
    {
        return self::fromArray($dto->toArray());
    }

    public function toArray(): array
    {
        return array_filter([
            'gameSlug' => $this->gameSlug,
            'serviceSlug' => $this->serviceSlug,
            'serviceType' => $this->serviceType,
            'orderType' => $this->orderType,
            'currentRank' => $this->currentRank,
            'currentDivision' => $this->currentDivision,
            'desiredRank' => $this->desiredRank,
            'desiredDivision' => $this->desiredDivision,
            'targetRank' => $this->targetRank,
            'targetDivision' => $this->targetDivision,
            'currentRR' => $this->currentRR,
            'avgRRPerWin' => $this->avgRRPerWin,
            'averageRR' => $this->averageRR,
            'region' => $this->region,
            'platform' => $this->platform,
            'boostMode' => $this->boostMode,
            'queueType' => $this->queueType,
            'accountType' => $this->accountType,
            'playType' => $this->playType,
            'currentLevel' => $this->currentLevel,
            'desiredLevel' => $this->desiredLevel,
            'selectedOptions' => $this->selectedOptions,
            'duoQueue' => $this->duoQueue,
            'streamGames' => $this->streamGames,
            'expressDelivery' => $this->expressDelivery,
            'addons' => $this->addons,
            'selectedAddons' => $this->selectedAddons,
            'specificAgents' => $this->specificAgents,
            'oneTrickAgent' => $this->oneTrickAgent,
            'wins' => $this->wins,
            'numberOfWins' => $this->numberOfWins,
            'placementGames' => $this->placementGames,
            'numberOfPlacementGames' => $this->numberOfPlacementGames,
            'clientTotal' => $this->clientTotal,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    public function evidenceInput(): array
    {
        return [
            'normalized' => $this->toArray(),
            'clientSubmittedTotals' => $this->clientSubmittedTotals,
        ];
    }

    protected static function clientSubmittedTotals(array $payload): array
    {
        $totals = [];

        foreach ([
            'clientTotal' => $payload['clientTotal'] ?? null,
            'client_total' => $payload['client_total'] ?? null,
            'pricing.total' => data_get($payload, 'pricing.total'),
            'finalPrice' => $payload['finalPrice'] ?? null,
        ] as $key => $value) {
            if (is_numeric($value)) {
                $totals[$key] = round((float) $value, 2);
            }
        }

        return $totals;
    }

    protected static function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value) && ! $value instanceof \Stringable) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    protected static function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    protected static function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    protected static function nullableBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    protected static function stringArray(mixed $value): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $entry): string => is_scalar($entry) || $entry instanceof \Stringable ? trim((string) $entry) : '',
            is_array($value) ? $value : []
        ), static fn (string $entry): bool => $entry !== ''));
    }

    protected static function structuredArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $sanitize = static function (mixed $entry) use (&$sanitize): mixed {
            if (is_array($entry)) {
                $sanitized = [];

                foreach ($entry as $key => $nested) {
                    if (! is_scalar($key) || in_array((string) $key, ['__proto__', 'constructor', 'prototype'], true)) {
                        continue;
                    }

                    $nestedValue = $sanitize($nested);

                    if ($nestedValue !== null && $nestedValue !== []) {
                        $sanitized[(string) $key] = $nestedValue;
                    }
                }

                return $sanitized;
            }

            if (is_scalar($entry) || $entry instanceof \Stringable) {
                $string = trim((string) $entry);

                return $string !== '' ? $string : null;
            }

            return null;
        };

        return (array) $sanitize($value);
    }
}
