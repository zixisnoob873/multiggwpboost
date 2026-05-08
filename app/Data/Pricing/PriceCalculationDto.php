<?php

namespace App\Data\Pricing;

final readonly class PriceCalculationDto
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
    ) {}

    public static function fromArray(array $payload): self
    {
        return new self(
            gameSlug: self::nullableString($payload['gameSlug'] ?? $payload['game_slug'] ?? $payload['game'] ?? null),
            serviceSlug: self::nullableString($payload['serviceSlug'] ?? $payload['service_slug'] ?? null),
            serviceType: self::nullableString($payload['serviceType'] ?? null),
            orderType: self::nullableString($payload['orderType'] ?? null),
            currentRank: self::nullableString($payload['currentRank'] ?? null),
            currentDivision: self::nullableString($payload['currentDivision'] ?? null),
            desiredRank: self::nullableString($payload['desiredRank'] ?? null),
            desiredDivision: self::nullableString($payload['desiredDivision'] ?? null),
            targetDivision: self::nullableString($payload['targetDivision'] ?? null),
            currentRR: self::nullableInt($payload['currentRR'] ?? null),
            avgRRPerWin: self::nullableString($payload['avgRRPerWin'] ?? null),
            averageRR: self::nullableString($payload['averageRR'] ?? null),
            region: self::nullableString($payload['region'] ?? null),
            platform: self::nullableString($payload['platform'] ?? null),
            boostMode: self::nullableString($payload['boostMode'] ?? null),
            queueType: self::nullableString($payload['queueType'] ?? $payload['queue_type'] ?? null),
            accountType: self::nullableString($payload['accountType'] ?? null),
            playType: self::nullableString($payload['playType'] ?? null),
            currentLevel: self::nullableInt($payload['currentLevel'] ?? $payload['current_level'] ?? null),
            desiredLevel: self::nullableInt($payload['desiredLevel'] ?? $payload['desired_level'] ?? null),
            selectedOptions: is_array($payload['selectedOptions'] ?? null) ? (array) $payload['selectedOptions'] : [],
            duoQueue: self::nullableBool($payload['duoQueue'] ?? $payload['duo_queue'] ?? null),
            streamGames: self::nullableBool($payload['streamGames'] ?? $payload['stream_games'] ?? null),
            expressDelivery: self::nullableBool($payload['expressDelivery'] ?? $payload['express_delivery'] ?? null),
            addons: self::stringArray($payload['addons'] ?? []),
            selectedAddons: self::stringArray($payload['selectedAddons'] ?? []),
            specificAgents: self::stringArray($payload['specificAgents'] ?? []),
            oneTrickAgent: self::stringArray($payload['oneTrickAgent'] ?? []),
            wins: self::nullableInt($payload['wins'] ?? null),
            numberOfWins: self::nullableInt($payload['numberOfWins'] ?? null),
            placementGames: self::nullableInt($payload['placementGames'] ?? null),
            numberOfPlacementGames: self::nullableInt($payload['numberOfPlacementGames'] ?? null),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'serviceType' => $this->serviceType,
            'gameSlug' => $this->gameSlug,
            'serviceSlug' => $this->serviceSlug,
            'orderType' => $this->orderType,
            'currentRank' => $this->currentRank,
            'currentDivision' => $this->currentDivision,
            'desiredRank' => $this->desiredRank,
            'desiredDivision' => $this->desiredDivision,
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
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    protected static function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    protected static function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
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
            static fn (mixed $entry): string => trim((string) $entry),
            is_array($value) ? $value : []
        ), static fn (string $entry): bool => $entry !== ''));
    }
}
