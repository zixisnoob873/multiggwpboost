<?php

namespace App\Support;

use Illuminate\Support\Arr;

class OrderLifecycleMetadata
{
    public static function eventKey(?string $previousStatus, ?string $nextStatus): ?string
    {
        return match ($nextStatus) {
            OrderStatus::IN_PROGRESS => $previousStatus === OrderStatus::PAUSED ? 'resumed' : 'assigned',
            OrderStatus::PAUSED => 'paused',
            OrderStatus::COMPLETED => 'completed',
            OrderStatus::CANCELLED => 'cancelled',
            OrderStatus::REFUNDED => 'refunded',
            default => null,
        };
    }

    public static function record(
        array $metadata,
        string $event,
        ?string $previousStatus,
        string $nextStatus,
        array $attributes = []
    ): array {
        $changedAt = now()->toIso8601String();
        $entry = array_filter([
            'event' => $event,
            'from' => $previousStatus,
            'to' => $nextStatus,
            'source' => self::clean($attributes['source'] ?? null, 80),
            'reason' => self::clean($attributes['reason'] ?? null, 500),
            'changed_at' => $attributes['changed_at'] ?? $changedAt,
            'actor_id' => $attributes['actor_id'] ?? null,
            'customer_action_required' => $attributes['customer_action_required'] ?? null,
            'next_step' => self::clean($attributes['next_step'] ?? null, 500),
            'refund' => self::cleanNested($attributes['refund'] ?? null),
            'completion' => self::cleanNested($attributes['completion'] ?? null),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);

        Arr::set($metadata, 'lifecycle.last_transition', $entry);
        Arr::set($metadata, 'lifecycle.'.$event, $entry);

        return $metadata;
    }

    protected static function clean(mixed $value, int $limit): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $limit);
    }

    protected static function cleanNested(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        return array_filter($value, fn ($item) => $item !== null && $item !== '' && $item !== []);
    }
}
