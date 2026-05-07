<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\User;
use App\Support\BoostingCatalog;
use App\Support\Pricing\PricingEngineManager;
use Illuminate\Support\Str;

class OrderPricingPayloadService
{
    protected array $orderCalculationCache = [];

    public function __construct(protected PricingEngineManager $pricingEngine) {}

    public function serviceType(Order $order): string
    {
        $details = $this->details($order);
        $baseOrder = $this->baseOrder($order);

        return (string) (
            BoostingCatalog::normalizeServiceType(
                $baseOrder['orderType']
                ?? $baseOrder['serviceType']
                ?? $details['service']
                ?? $order->product
            )
            ?? (
                $baseOrder['orderType']
                ?? $baseOrder['serviceType']
                ?? $details['service']
                ?? $order->product
                ?? 'Rank Boosting'
            )
        );
    }

    public function basePayload(Order $order): array
    {
        $details = $this->details($order);

        return $this->payloadFromDetails($details, $this->serviceType($order));
    }

    public function calculateForOrder(Order $order): array
    {
        $cacheKey = implode('|', [
            (string) $order->getKey(),
            (string) ($order->updated_at?->timestamp ?? '0'),
            (string) ($order->price_cents ?? '0'),
        ]);

        if (array_key_exists($cacheKey, $this->orderCalculationCache)) {
            return $this->orderCalculationCache[$cacheKey];
        }

        $payload = $this->basePayload($order);

        return $this->orderCalculationCache[$cacheKey] = $this->pricingEngine->calculateOrFail($payload, $this->pricingOptions($payload));
    }

    public function calculate(array $payload): array
    {
        return $this->pricingEngine->calculateOrFail($payload, $this->pricingOptions($payload));
    }

    public function payloadFromDetails(array $details, ?string $serviceType = null): array
    {
        $baseOrder = $this->baseOrderFromDetails($details);
        $resolvedServiceType = (string) (
            $serviceType
            ?? $baseOrder['orderType']
            ?? $baseOrder['serviceType']
            ?? $details['service']
            ?? 'Rank Boosting'
        );
        $rawDesiredDivision = $baseOrder['desiredDivision'] ?? $baseOrder['targetDivision'] ?? $baseOrder['targetRank'] ?? $details['to'] ?? null;

        return array_filter([
            'gameSlug' => BoostingCatalog::gameSlugFromPayload($baseOrder + $details),
            'serviceType' => $resolvedServiceType,
            'currentDivision' => $this->resolvedRankValue(
                $baseOrder['currentDivision']
                ?? $baseOrder['currentRank']
                ?? $details['from']
                ?? $details['currentRank']
                ?? null
            ),
            'targetDivision' => $resolvedServiceType === 'Radiant Boost'
                ? 'Radiant'
                : $this->resolvedRankValue($rawDesiredDivision),
            'currentRR' => $this->integerValue(
                $baseOrder['currentRR']
                ?? $details['currentRR']
                ?? $details['rr']
                ?? data_get($details, 'progress.currentRR')
                ?? null
            ),
            'avgRRPerWin' => $baseOrder['avgRRPerWin']
                ?? $baseOrder['averageRR']
                ?? $baseOrder['avg_rr']
                ?? $details['averageRR']
                ?? $details['average_rr']
                ?? null,
            'region' => $baseOrder['region'] ?? $details['region'] ?? null,
            'platform' => $baseOrder['platform'] ?? $details['platform'] ?? null,
            'boostMode' => $baseOrder['boostMode']
                ?? $baseOrder['accountType']
                ?? $baseOrder['playType']
                ?? $details['accountType']
                ?? $details['boostMode']
                ?? $details['playType']
                ?? null,
            'selectedAddons' => BoostingCatalog::normalizeAddons($baseOrder['addons'] ?? $details['addons'] ?? []),
            'specificAgents' => BoostingCatalog::normalizeSpecificAgents($baseOrder['specificAgents'] ?? $details['specificAgents'] ?? []),
            'oneTrickAgent' => BoostingCatalog::normalizeOneTrickAgent($baseOrder['oneTrickAgent'] ?? $details['oneTrickAgent'] ?? []),
            'numberOfWins' => $this->integerValue($baseOrder['numberOfWins'] ?? $details['numberOfWins'] ?? $this->numberFromLabel($rawDesiredDivision, 'wins')),
            'numberOfPlacementGames' => $this->integerValue($baseOrder['numberOfPlacementGames'] ?? $details['numberOfPlacementGames'] ?? $this->numberFromLabel($rawDesiredDivision, 'placement')),
        ], fn ($value) => $value !== null && $value !== '');
    }

    public function payloadFromAdminInput(array $data): array
    {
        $serviceType = (string) ($data['product'] ?? 'Rank Boosting');
        $desiredDivision = $serviceType === 'Radiant Boost'
            ? 'Radiant'
            : ($data['desired_division'] ?? null);

        return array_filter([
            'gameSlug' => BoostingCatalog::normalizeGameSlug($data['game'] ?? $data['gameSlug'] ?? null),
            'serviceType' => $serviceType,
            'currentDivision' => $data['current_division'] ?? null,
            'targetDivision' => $desiredDivision,
            'currentRR' => $this->integerValue($data['current_rr'] ?? null),
            'avgRRPerWin' => $data['average_rr'] ?? null,
            'region' => $data['region'] ?? null,
            'platform' => $data['platform'] ?? null,
            'boostMode' => $data['account_type'] ?? null,
            'selectedAddons' => BoostingCatalog::normalizeAddons($data['addons'] ?? []),
            'specificAgents' => BoostingCatalog::normalizeSpecificAgents($data['specific_agents'] ?? $data['specificAgents'] ?? []),
            'oneTrickAgent' => BoostingCatalog::normalizeOneTrickAgent($data['one_trick_agent'] ?? $data['oneTrickAgent'] ?? []),
            'numberOfWins' => $this->integerValue($data['number_of_wins'] ?? $data['numberOfWins'] ?? null),
            'numberOfPlacementGames' => $this->integerValue($data['number_of_placement_games'] ?? $data['numberOfPlacementGames'] ?? null),
        ], fn ($value) => $value !== null && $value !== '');
    }

    public function canAuthoritativelyPrice(array $payload): bool
    {
        $serviceType = BoostingCatalog::normalizeServiceType($payload['serviceType'] ?? $payload['orderType'] ?? null);
        $serviceKind = BoostingCatalog::serviceKind($serviceType);

        if (! $serviceType || ! $serviceKind) {
            return false;
        }

        $hasBaseFields = filled($payload['currentDivision'] ?? null)
            && filled($payload['region'] ?? null)
            && filled($payload['platform'] ?? null)
            && filled($payload['boostMode'] ?? null);

        if (! $hasBaseFields) {
            return false;
        }

        return match ($serviceKind) {
            'rank_boost' => filled($payload['targetDivision'] ?? null)
                && array_key_exists('currentRR', $payload)
                && filled($payload['avgRRPerWin'] ?? null),
            'radiant_boost' => filled($payload['currentDivision'] ?? null)
                && filled($payload['avgRRPerWin'] ?? null),
            'ranked_wins' => array_key_exists('numberOfWins', $payload),
            'placement_matches' => array_key_exists('numberOfPlacementGames', $payload),
            default => false,
        };
    }

    public function syncOrderDetails(Order $order, array $pricedPayload): array
    {
        $details = $this->details($order);
        $serviceType = (string) ($pricedPayload['orderType'] ?? $pricedPayload['serviceType'] ?? $this->serviceType($order));

        return $this->syncDetails($details, $pricedPayload, $serviceType);
    }

    public function syncDetails(array $details, array $pricedPayload, ?string $serviceType = null): array
    {
        $serviceType = (string) ($pricedPayload['orderType'] ?? $pricedPayload['serviceType'] ?? $serviceType ?? 'Rank Boosting');
        $addons = BoostingCatalog::normalizeAddons($pricedPayload['addons'] ?? $pricedPayload['selectedAddons'] ?? []);

        $details['service'] = $serviceType;
        $details['from'] = $pricedPayload['currentDivision'] ?? ($details['from'] ?? null);
        $details['to'] = $pricedPayload['desiredDivision'] ?? ($details['to'] ?? null);
        $details['currentRR'] = $pricedPayload['currentRR'] ?? ($details['currentRR'] ?? null);
        $details['averageRR'] = $pricedPayload['averageRR'] ?? ($details['averageRR'] ?? null);
        $details['region'] = $pricedPayload['region'] ?? ($details['region'] ?? null);
        $details['platform'] = $pricedPayload['platform'] ?? ($details['platform'] ?? null);
        $details['accountType'] = $pricedPayload['accountType'] ?? ($details['accountType'] ?? null);
        $details['numberOfWins'] = $pricedPayload['numberOfWins'] ?? ($details['numberOfWins'] ?? null);
        $details['numberOfPlacementGames'] = $pricedPayload['numberOfPlacementGames'] ?? ($details['numberOfPlacementGames'] ?? null);
        $details['addons'] = $addons;
        $details['specificAgents'] = BoostingCatalog::normalizeSpecificAgents($pricedPayload['specificAgents'] ?? $details['specificAgents'] ?? []);
        $details['oneTrickAgent'] = BoostingCatalog::normalizeOneTrickAgent($pricedPayload['oneTrickAgent'] ?? $details['oneTrickAgent'] ?? []);
        $details['order'] = BoostingCatalog::sanitizeOrderPayload($pricedPayload);

        return $details;
    }

    public function contactData(Order $order, User $customer): array
    {
        [$firstName, $lastName] = $this->customerNames($customer);

        return [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => (string) $customer->email,
            'contactMethod' => (string) ($order->contact_method ?: 'email'),
            'whatsapp' => $order->whatsapp,
            'discord' => $order->discord,
        ];
    }

    public function higherRanks(?string $currentTarget): array
    {
        $rankOrder = config('pricing.rank_order', []);
        $currentIndex = array_search($this->rankOnlyValue($currentTarget), $rankOrder, true);

        if ($currentIndex === false) {
            return $rankOrder;
        }

        return array_slice($rankOrder, $currentIndex + 1);
    }

    public function lowerRanks(?string $currentRank): array
    {
        $rankOrder = config('pricing.rank_order', []);
        $currentIndex = array_search($this->rankOnlyValue($currentRank), $rankOrder, true);

        if ($currentIndex === false || $currentIndex === 0) {
            return [];
        }

        return array_reverse(array_slice($rankOrder, 0, $currentIndex));
    }

    public function normalizedPayoutRate(Order $order): float
    {
        $storedRate = $order->booster_payout_rate;
        if ($storedRate === null) {
            return Order::configuredBoosterPayoutRate();
        }

        $rate = (float) $storedRate;

        return $rate > 1 ? max(0, $rate / 100) : max(0, $rate);
    }

    public function payoutPercentage(Order $order): float
    {
        $storedRate = $order->booster_payout_rate;
        if ($storedRate === null) {
            return Order::configuredBoosterPayoutPercentage();
        }

        $rate = (float) $storedRate;

        return $rate > 1 ? max(0, $rate) : max(0, $rate * 100);
    }

    protected function details(Order $order): array
    {
        $details = $order->details;

        return is_array($details) ? $details : (json_decode((string) $details, true) ?: []);
    }

    protected function baseOrder(Order $order): array
    {
        $details = $this->details($order);
        $baseOrder = $this->baseOrderFromDetails($details);

        return $baseOrder;
    }

    protected function baseOrderFromDetails(array $details): array
    {
        $baseOrder = $details['order'] ?? [];

        return is_array($baseOrder) ? $baseOrder : [];
    }

    protected function customerNames(User $customer): array
    {
        $firstName = trim((string) ($customer->first_name ?? ''));
        $lastName = trim((string) ($customer->last_name ?? ''));

        if ($firstName !== '' || $lastName !== '') {
            return [$firstName !== '' ? $firstName : 'Customer', $lastName];
        }

        $parts = preg_split('/\s+/', trim((string) ($customer->name ?? 'Customer'))) ?: [];

        return [
            $parts[0] ?? 'Customer',
            count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '',
        ];
    }

    protected function integerValue(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (preg_match('/(\d+)/', (string) $value, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    protected function numberFromLabel(mixed $value, string $type): ?int
    {
        $text = Str::lower(trim((string) $value));
        if ($text === '') {
            return null;
        }

        $needle = $type === 'placement' ? 'placement' : 'win';
        if (! str_contains($text, $needle)) {
            return null;
        }

        return $this->integerValue($text);
    }

    protected function rankOnlyValue(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        if (preg_match('/^\d+\s+(wins?|placement matches?)$/i', $text) === 1) {
            return null;
        }

        $rankOrder = config('pricing.rank_order', []);
        $normalized = Str::of($text)->trim()->replaceMatches('/\s+/', ' ')->title()->value();

        foreach ($rankOrder as $rank) {
            if (Str::lower($rank) === Str::lower($normalized)) {
                return $rank;
            }
        }

        return $normalized;
    }

    protected function resolvedRankValue(mixed $value): ?string
    {
        $canonical = BoostingCatalog::canonicalRankLabel($value);

        return $canonical ?? $this->rankOnlyValue($value);
    }

    protected function pricingOptions(array $payload): array
    {
        $serviceType = (string) ($payload['serviceType'] ?? $payload['orderType'] ?? '');

        return [
            'allowExtendedRankedWins' => $serviceType === 'Ranked Wins',
        ];
    }
}
