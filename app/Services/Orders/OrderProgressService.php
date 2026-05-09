<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class OrderProgressService
{
    public function __construct(protected OrderPricingPayloadService $payloadService) {}

    public function snapshot(Order $order): array
    {
        $details = $this->details($order);
        $progress = $this->progress($details);
        $baseOrder = $this->payloadService->basePayload($order);
        $serviceType = $this->payloadService->serviceType($order);
        $serviceKind = $this->serviceKind($serviceType);

        return match ($serviceKind) {
            'ranked_wins' => $this->rankedWinsSnapshot($serviceType, $details, $progress, $baseOrder),
            'placement_matches' => $this->placementsSnapshot($serviceType, $details, $progress, $baseOrder),
            default => $this->rankSnapshot($serviceType, $serviceKind, $details, $progress, $baseOrder),
        };
    }

    public function initializeDetails(Order $order): array
    {
        $details = $this->details($order);

        if (is_numeric(data_get($details, 'progress.pct'))) {
            return $details;
        }

        $snapshot = $this->snapshot($order);
        $details['progress'] = $this->initialProgressPayload($snapshot);

        if (array_key_exists('currentRank', $details['progress'])) {
            $details['currentRank'] = $details['progress']['currentRank'];
        }

        if (array_key_exists('currentRR', $details['progress'])) {
            $details['currentRR'] = $details['progress']['currentRR'];
        }

        if (array_key_exists('completedWins', $details['progress'])) {
            $details['completedWins'] = $details['progress']['completedWins'];
        }

        if (array_key_exists('completedPlacements', $details['progress'])) {
            $details['completedPlacements'] = $details['progress']['completedPlacements'];
        }

        return $details;
    }

    public function update(Order $order, ?Authenticatable $actor, array $input): Order
    {
        $progressPayload = $this->progressPayload($order, $actor, $input);
        $details = $this->applyProgressPayload($order, $progressPayload);

        $order->forceFill([
            'details' => $details,
        ])->save();

        return $order->refresh();
    }

    public function progressPayload(Order $order, ?Authenticatable $actor, array $input): array
    {
        $snapshot = $this->snapshot($order);
        $actorName = $this->actorLabel($actor);
        $actorRole = trim((string) ($actor?->role ?? 'system'));

        $payload = match ($snapshot['serviceKind']) {
            'ranked_wins' => $this->rankedWinsProgressPayload($snapshot, $input),
            'placement_matches' => $this->placementsProgressPayload($snapshot, $input),
            default => $this->rankProgressPayload($snapshot, $input),
        };

        $payload['updatedAt'] = now()->toDateTimeString();
        $payload['updatedBy'] = $actorName !== '' ? $actorName : 'System';
        $payload['updatedByRole'] = $actorRole !== '' ? $actorRole : 'system';

        return $payload;
    }

    public function completionPayload(Order $order, ?Authenticatable $actor = null): array
    {
        $snapshot = $this->snapshot($order);
        $actorName = $this->actorLabel($actor);
        $actorRole = trim((string) ($actor?->role ?? 'system'));

        $payload = match ($snapshot['serviceKind']) {
            'ranked_wins' => [
                'pct' => 100,
                'completedWins' => $snapshot['totalWins'],
                'winsCompleted' => $snapshot['totalWins'],
            ],
            'placement_matches' => [
                'pct' => 100,
                'completedPlacements' => $snapshot['totalPlacements'],
                'placementsCompleted' => $snapshot['totalPlacements'],
            ],
            default => [
                'pct' => 100,
                'currentRank' => $snapshot['targetRank'],
                'currentRR' => $snapshot['targetRR'],
            ],
        };

        $payload['updatedAt'] = now()->toDateTimeString();
        $payload['updatedBy'] = $actorName !== '' ? $actorName : 'System';
        $payload['updatedByRole'] = $actorRole !== '' ? $actorRole : 'system';

        return $payload;
    }

    public function applyProgressPayload(Order $order, array $progressPayload): array
    {
        $details = $this->details($order);
        $details['progress'] = $progressPayload;
        $details['progressUpdatedAt'] = $progressPayload['updatedAt'] ?? now()->toDateTimeString();

        if (array_key_exists('currentRank', $progressPayload)) {
            $details['currentRank'] = $progressPayload['currentRank'];
        }

        if (array_key_exists('currentRR', $progressPayload)) {
            $details['currentRR'] = $progressPayload['currentRR'];
        }

        if (array_key_exists('completedWins', $progressPayload)) {
            $details['completedWins'] = $progressPayload['completedWins'];
        }

        if (array_key_exists('completedPlacements', $progressPayload)) {
            $details['completedPlacements'] = $progressPayload['completedPlacements'];
        }

        return $details;
    }

    protected function rankSnapshot(string $serviceType, string $serviceKind, array $details, array $progress, array $baseOrder): array
    {
        $startRank = $this->canonicalRank($baseOrder['currentDivision'] ?? $baseOrder['currentRank'] ?? $details['from'] ?? 'Unranked');
        $targetRank = $serviceKind === 'radiant_boost'
            ? 'Radiant'
            : $this->canonicalRank($baseOrder['desiredDivision'] ?? $baseOrder['targetDivision'] ?? $details['to'] ?? $startRank);
        $startRr = $this->normalizeRr($baseOrder['currentRR'] ?? 0);
        $targetRr = $this->normalizeRr($baseOrder['targetRR'] ?? $baseOrder['desiredRR'] ?? 0);
        if ($targetRank === 'Radiant') {
            $targetRr = 0;
        }

        $startPoints = $this->rankPoints($startRank, $startRr);
        $targetPoints = max($startPoints, $this->rankPoints($targetRank, $targetRr));
        $existingRank = $this->canonicalRank($progress['currentRank'] ?? $details['currentRank'] ?? $startRank);
        $existingRr = $this->normalizeRr($progress['currentRR'] ?? $details['currentRR'] ?? $startRr);
        $existingPoints = $this->clamp($this->rankPoints($existingRank, $existingRr), $startPoints, $targetPoints);
        $currentPosition = $this->pointsToRankPosition($existingPoints);
        $minRankIndex = $this->rankIndex($startRank);
        $targetRankIndex = $this->rankIndex($targetRank);

        $rankOptions = [];
        foreach (config('pricing.rank_order', []) as $index => $rank) {
            if ($index < $minRankIndex || $index > $targetRankIndex) {
                continue;
            }

            $rankOptions[] = $rank;
        }

        return [
            'serviceType' => $serviceType,
            'serviceKind' => $serviceKind,
            'mode' => 'rank',
            'showCurrentRank' => true,
            'showCurrentRr' => true,
            'showCompletedWins' => false,
            'showCompletedPlacements' => false,
            'startRank' => $startRank,
            'targetRank' => $targetRank,
            'startRR' => $startRr,
            'targetRR' => $targetRr,
            'startPoints' => $startPoints,
            'targetPoints' => $targetPoints,
            'currentRank' => $currentPosition['rank'],
            'currentRR' => $currentPosition['rr'],
            'currentPoints' => $existingPoints,
            'rankOptions' => $rankOptions,
            'progressPct' => $this->percentage($existingPoints - $startPoints, max(1, $targetPoints - $startPoints)),
            'currentSummary' => $currentPosition['rr'] !== null ? sprintf('%s RR', $currentPosition['rr']) : null,
        ];
    }

    protected function rankedWinsSnapshot(string $serviceType, array $details, array $progress, array $baseOrder): array
    {
        $totalWins = max(0, (int) ($baseOrder['numberOfWins'] ?? $this->extractCount($baseOrder['desiredDivision'] ?? $details['to'] ?? null, 'win')));
        $existing = max(
            0,
            min(
                $totalWins,
                (int) ($progress['completedWins'] ?? $progress['winsCompleted'] ?? $details['completedWins'] ?? 0)
            )
        );

        return [
            'serviceType' => $serviceType,
            'serviceKind' => 'ranked_wins',
            'mode' => 'ranked_wins',
            'showCurrentRank' => false,
            'showCurrentRr' => false,
            'showCompletedWins' => true,
            'showCompletedPlacements' => false,
            'totalWins' => $totalWins,
            'completedWins' => $existing,
            'progressPct' => $this->percentage($existing, max(1, $totalWins)),
            'currentSummary' => sprintf('%d / %d Wins', $existing, $totalWins),
        ];
    }

    protected function placementsSnapshot(string $serviceType, array $details, array $progress, array $baseOrder): array
    {
        $totalPlacements = max(
            0,
            (int) ($baseOrder['numberOfPlacementGames'] ?? $this->extractCount($baseOrder['desiredDivision'] ?? $details['to'] ?? null, 'placement'))
        );
        $existing = max(
            0,
            min(
                $totalPlacements,
                (int) ($progress['completedPlacements'] ?? $progress['placementsCompleted'] ?? $details['completedPlacements'] ?? 0)
            )
        );

        return [
            'serviceType' => $serviceType,
            'serviceKind' => 'placement_matches',
            'mode' => 'placements',
            'showCurrentRank' => false,
            'showCurrentRr' => false,
            'showCompletedWins' => false,
            'showCompletedPlacements' => true,
            'totalPlacements' => $totalPlacements,
            'completedPlacements' => $existing,
            'progressPct' => $this->percentage($existing, max(1, $totalPlacements)),
            'currentSummary' => sprintf('%d / %d Matches', $existing, $totalPlacements),
        ];
    }

    protected function rankProgressPayload(array $snapshot, array $input): array
    {
        $submittedRank = $this->canonicalRank($input['current_rank'] ?? $snapshot['currentRank']);
        $submittedRr = $this->normalizeRr($input['current_rr'] ?? $snapshot['currentRR']);
        $submittedPoints = $this->rankPoints($submittedRank, $submittedRr);
        $clampedPoints = $this->clamp($submittedPoints, $snapshot['startPoints'], $snapshot['targetPoints']);
        $position = $this->pointsToRankPosition($clampedPoints);

        return [
            'pct' => $this->percentage(
                $clampedPoints - $snapshot['startPoints'],
                max(1, $snapshot['targetPoints'] - $snapshot['startPoints'])
            ),
            'currentRank' => $position['rank'],
            'currentRR' => $position['rr'],
        ];
    }

    protected function rankedWinsProgressPayload(array $snapshot, array $input): array
    {
        $submittedWins = max(0, (int) ($input['completed_wins'] ?? $snapshot['completedWins']));
        $completedWins = $this->clamp($submittedWins, 0, $snapshot['totalWins']);

        return [
            'pct' => $this->percentage($completedWins, max(1, $snapshot['totalWins'])),
            'completedWins' => $completedWins,
            'winsCompleted' => $completedWins,
        ];
    }

    protected function placementsProgressPayload(array $snapshot, array $input): array
    {
        $submittedPlacements = max(0, (int) ($input['completed_placements'] ?? $snapshot['completedPlacements']));
        $completedPlacements = $this->clamp($submittedPlacements, 0, $snapshot['totalPlacements']);

        return [
            'pct' => $this->percentage($completedPlacements, max(1, $snapshot['totalPlacements'])),
            'completedPlacements' => $completedPlacements,
            'placementsCompleted' => $completedPlacements,
        ];
    }

    protected function details(Order $order): array
    {
        return is_array($order->details) ? $order->details : (json_decode((string) $order->details, true) ?: []);
    }

    protected function progress(array $details): array
    {
        $progress = $details['progress'] ?? [];

        return is_array($progress) ? $progress : [];
    }

    protected function serviceKind(string $serviceType): string
    {
        return (string) (config("pricing.services.{$serviceType}.kind") ?? match ($serviceType) {
            'Ranked Wins' => 'ranked_wins',
            'Placement Matches' => 'placement_matches',
            'Radiant Boost' => 'radiant_boost',
            default => 'rank_boost',
        });
    }

    protected function canonicalRank(mixed $value): string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return 'Unranked';
        }

        foreach (config('pricing.rank_order', []) as $rank) {
            if (strcasecmp($rank, $normalized) === 0) {
                return $rank;
            }
        }

        $normalized = preg_replace('/\b1\b/i', 'I', $normalized) ?? $normalized;
        $normalized = preg_replace('/\b2\b/i', 'II', $normalized) ?? $normalized;
        $normalized = preg_replace('/\b3\b/i', 'III', $normalized) ?? $normalized;
        $normalized = ucwords(strtolower(trim($normalized)));
        $normalized = str_replace(['Ii', 'Iii'], ['II', 'III'], $normalized);

        foreach (config('pricing.rank_order', []) as $rank) {
            if (strcasecmp($rank, $normalized) === 0) {
                return $rank;
            }
        }

        return 'Unranked';
    }

    protected function normalizeRr(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 0;
        }

        return $this->clamp((int) $value, 0, 100);
    }

    protected function rankPoints(string $rank, int $rr): int
    {
        $index = $this->rankIndex($rank);
        if ($index < 0) {
            return 0;
        }

        if ($rank === 'Radiant') {
            return $index * 100;
        }

        return ($index * 100) + $this->clamp($rr, 0, 100);
    }

    protected function pointsToRankPosition(int $points): array
    {
        $ranks = array_values(config('pricing.rank_order', []));
        if ($ranks === []) {
            return ['rank' => 'Unranked', 'rr' => 0];
        }

        $maxIndex = count($ranks) - 1;
        $rankIndex = $this->clamp((int) floor(max(0, $points) / 100), 0, $maxIndex);
        $rank = $ranks[$rankIndex] ?? 'Unranked';

        return [
            'rank' => $rank,
            'rr' => $rank === 'Radiant' ? 0 : $this->clamp($points - ($rankIndex * 100), 0, 100),
        ];
    }

    protected function rankIndex(string $rank): int
    {
        $index = array_search($rank, config('pricing.rank_order', []), true);

        return $index === false ? 0 : $index;
    }

    protected function percentage(int|float $value, int|float $total): int
    {
        if ($total <= 0) {
            return 0;
        }

        return $this->clamp((int) round(($value / $total) * 100), 0, 100);
    }

    protected function extractCount(mixed $value, string $type): int
    {
        $text = strtolower(trim((string) $value));
        if ($text === '') {
            return 0;
        }

        $needle = $type === 'placement' ? 'placement' : 'win';
        if (! str_contains($text, $needle)) {
            return 0;
        }

        $matches = $this->matches('/(\d+)/', $text);

        return isset($matches[0]) ? (int) $matches[0] : 0;
    }

    protected function matches(string $pattern, string $subject): array
    {
        preg_match_all($pattern, $subject, $matches);

        return $matches[1] ?? [];
    }

    protected function clamp(int|float $value, int|float $min, int|float $max): int
    {
        return (int) max($min, min($max, $value));
    }

    protected function initialProgressPayload(array $snapshot): array
    {
        return match ($snapshot['serviceKind']) {
            'ranked_wins' => [
                'pct' => 0,
                'completedWins' => 0,
                'winsCompleted' => 0,
            ],
            'placement_matches' => [
                'pct' => 0,
                'completedPlacements' => 0,
                'placementsCompleted' => 0,
            ],
            default => [
                'pct' => 0,
                'currentRank' => $snapshot['startRank'],
                'currentRR' => $snapshot['startRR'],
            ],
        };
    }

    protected function actorLabel(?Authenticatable $actor): string
    {
        if (method_exists($actor, 'fullIdentity') && User::normalizeRole($actor?->role) === User::ROLE_SUPER_ADMIN) {
            return trim((string) $actor->fullIdentity('System'));
        }

        if (method_exists($actor, 'publicIdentity')) {
            return trim((string) $actor->publicIdentity('System'));
        }

        return trim((string) ($actor?->name ?? 'System'));
    }
}
