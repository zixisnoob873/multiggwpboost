<?php

namespace App\Queries;

use App\Models\Order;
use App\Support\OrderStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PublicSocialProofQuery
{
    protected const ICON_BASE_URL = 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/%d/largeicon.png';

    protected const RANK_TIER_MAP = [
        'unranked' => 0,
        'iron i' => 3,
        'iron ii' => 4,
        'iron iii' => 5,
        'bronze i' => 6,
        'bronze ii' => 7,
        'bronze iii' => 8,
        'silver i' => 9,
        'silver ii' => 10,
        'silver iii' => 11,
        'gold i' => 12,
        'gold ii' => 13,
        'gold iii' => 14,
        'platinum i' => 15,
        'platinum ii' => 16,
        'platinum iii' => 17,
        'diamond i' => 18,
        'diamond ii' => 19,
        'diamond iii' => 20,
        'ascendant i' => 21,
        'ascendant ii' => 22,
        'ascendant iii' => 23,
        'immortal i' => 24,
        'immortal ii' => 25,
        'immortal iii' => 26,
        'radiant' => 27,
    ];

    public function execute(int $limit = 12): array
    {
        if (! Schema::hasTable('orders')) {
            return [];
        }

        return Cache::remember("public-social-proof.orders.{$limit}", now()->addSeconds(45), function () use ($limit): array {
            return Order::query()
                ->with(['user:id,name,first_name,last_name,email'])
                ->where('payment_status', 'paid')
                ->whereNotIn('status', [OrderStatus::CANCELLED, OrderStatus::REFUNDED])
                ->orderByDesc('paid_at')
                ->orderByDesc('created_at')
                ->limit(max($limit * 3, 18))
                ->get(['id', 'user_id', 'product', 'status', 'payment_status', 'details', 'metadata', 'paid_at', 'created_at'])
                ->map(fn (Order $order): ?array => $this->toItem($order))
                ->filter()
                ->take($limit)
                ->values()
                ->all();
        });
    }

    protected function toItem(Order $order): ?array
    {
        $details = is_array($order->details) ? $order->details : [];
        $baseOrder = Arr::get($details, 'order', []);
        $baseOrder = is_array($baseOrder) ? $baseOrder : [];
        $metadata = is_array($order->metadata) ? $order->metadata : [];

        $service = $this->normalizeService(
            $details['service']
                ?? $baseOrder['orderType']
                ?? $order->product
                ?? 'Rank Boosting'
        );

        $fromRank = $this->buildRankDisplay($this->firstFilled([
            $details['from'] ?? null,
            $details['currentRank'] ?? null,
            Arr::get($details, 'progress.currentRank'),
            $baseOrder['currentDivision'] ?? null,
            $baseOrder['currentRank'] ?? null,
            $baseOrder['from'] ?? null,
        ]));

        $toValue = $this->firstFilled([
            $details['to'] ?? null,
            $baseOrder['desiredDivision'] ?? null,
            $baseOrder['to'] ?? null,
        ]);

        $toRank = $this->buildRankDisplay($toValue);
        $goal = $toRank ? null : $this->normalizeGoal($toValue);
        $occurredAt = $order->paid_at ?? $order->created_at;

        if (! $fromRank || (! $toRank && ! $goal) || ! $occurredAt) {
            return null;
        }

        $customerName = $this->customerDisplayName($order, $metadata);

        return [
            'id' => (int) $order->id,
            'customer' => $customerName,
            'initials' => $this->initials($customerName),
            'service' => $service,
            'timeLabel' => $this->relativeTime($occurredAt),
            'occurredAt' => $occurredAt->toIso8601String(),
            'from' => $fromRank,
            'to' => $toRank,
            'goal' => $goal,
        ];
    }

    protected function normalizeService(?string $service): string
    {
        $value = Str::of((string) $service)->trim()->lower()->value();

        return match (true) {
            str_contains($value, 'placement') => 'Placements',
            str_contains($value, 'ranked win'), str_contains($value, 'net-win'), str_contains($value, 'net win') => 'Net-Wins',
            str_contains($value, 'radiant') => 'Radiant Boost',
            default => 'Rank Boosting',
        };
    }

    protected function buildRankDisplay(?string $value): ?array
    {
        $normalized = $this->normalizeRank($value);

        if (! $normalized) {
            return null;
        }

        return [
            'label' => $this->titleCaseRank($normalized),
            'icon' => sprintf(self::ICON_BASE_URL, self::RANK_TIER_MAP[$normalized]),
        ];
    }

    protected function normalizeRank(?string $value): ?string
    {
        $clean = Str::of((string) $value)
            ->lower()
            ->trim()
            ->replaceMatches('/\s+/', ' ')
            ->value();

        if ($clean === '') {
            return null;
        }

        if (array_key_exists($clean, self::RANK_TIER_MAP)) {
            return $clean;
        }

        $numeric = str_replace(
            [' 1', ' 2', ' 3'],
            [' i', ' ii', ' iii'],
            $clean
        );

        if (array_key_exists($numeric, self::RANK_TIER_MAP)) {
            return $numeric;
        }

        if (str_contains($clean, 'radiant')) {
            return 'radiant';
        }

        if (str_contains($clean, 'unranked')) {
            return 'unranked';
        }

        return null;
    }

    protected function normalizeGoal(?string $value): ?string
    {
        $goal = trim((string) $value);

        return $goal !== '' ? $goal : null;
    }

    protected function titleCaseRank(string $rank): string
    {
        return collect(explode(' ', $rank))
            ->map(function (string $segment): string {
                if (in_array($segment, ['i', 'ii', 'iii'], true)) {
                    return strtoupper($segment);
                }

                return ucfirst($segment);
            })
            ->implode(' ');
    }

    protected function customerDisplayName(Order $order, array $metadata): string
    {
        $metaCustomer = is_array($metadata['customer'] ?? null) ? $metadata['customer'] : [];

        $firstName = $this->firstFilled([
            $metaCustomer['firstName'] ?? null,
            $order->user?->first_name ?? null,
            $this->firstWord($order->user?->name),
        ]);

        $lastName = $this->firstFilled([
            $metaCustomer['lastName'] ?? null,
            $order->user?->last_name ?? null,
            $this->lastWord($order->user?->name),
        ]);

        if ($firstName && $lastName) {
            return trim($firstName).' '.strtoupper(Str::substr(trim($lastName), 0, 1)).'.';
        }

        if ($firstName) {
            return trim($firstName);
        }

        return 'Customer';
    }

    protected function initials(string $name): string
    {
        $letters = collect(preg_split('/\s+/', str_replace('.', '', trim($name))) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => strtoupper(Str::substr($part, 0, 1)))
            ->implode('');

        return $letters !== '' ? $letters : 'C';
    }

    protected function relativeTime(CarbonInterface $value): string
    {
        return $value->diffForHumans(now(), [
            'parts' => 1,
            'short' => false,
            'syntax' => CarbonInterface::DIFF_RELATIVE_TO_NOW,
        ]);
    }

    protected function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            $string = trim((string) $value);

            if ($string !== '') {
                return $string;
            }
        }

        return null;
    }

    protected function firstWord(?string $value): ?string
    {
        $parts = preg_split('/\s+/', trim((string) $value)) ?: [];

        return $parts[0] ?? null;
    }

    protected function lastWord(?string $value): ?string
    {
        $parts = preg_split('/\s+/', trim((string) $value)) ?: [];

        return count($parts) > 1 ? end($parts) ?: null : null;
    }
}
