<?php

namespace App\Data\Pricing;

final readonly class PriceCalculationDto
{
    public function __construct(
        public ?string $gameSlug,
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
        public ?string $accountType,
        public ?string $playType,
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
            accountType: self::nullableString($payload['accountType'] ?? null),
            playType: self::nullableString($payload['playType'] ?? null),
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
            'accountType' => $this->accountType,
            'playType' => $this->playType,
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

    protected static function stringArray(mixed $value): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $entry): string => trim((string) $entry),
            is_array($value) ? $value : []
        ), static fn (string $entry): bool => $entry !== ''));
    }
}
