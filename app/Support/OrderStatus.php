<?php

namespace App\Support;

class OrderStatus
{
    public const PENDING = 'Pending';

    public const IN_PROGRESS = 'InProgress';

    public const PAUSED = 'Paused';

    public const COMPLETED = 'Completed';

    public const CANCELLED = 'Cancelled';

    public const REFUNDED = 'Refunded';

    public static function options(): array
    {
        return [
            self::PENDING => 'Pending',
            self::IN_PROGRESS => 'In Progress',
            self::PAUSED => 'Paused',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::REFUNDED => 'Refunded',
        ];
    }

    public static function values(): array
    {
        return array_keys(self::options());
    }

    public static function boosterUpdateOptions(): array
    {
        return [
            self::IN_PROGRESS => self::label(self::IN_PROGRESS),
        ];
    }

    public static function boosterUpdateOptionsFor(?string $currentStatus): array
    {
        return match ($currentStatus) {
            self::PENDING => [
                self::IN_PROGRESS => self::label(self::IN_PROGRESS),
            ],
            default => [],
        };
    }

    public static function activeValues(): array
    {
        return [
            self::PENDING,
            self::IN_PROGRESS,
            self::PAUSED,
        ];
    }

    public static function closedValues(): array
    {
        return [
            self::COMPLETED,
            self::CANCELLED,
            self::REFUNDED,
        ];
    }

    public static function boosterWorkspaceValues(): array
    {
        return [
            self::PENDING,
            self::IN_PROGRESS,
            self::PAUSED,
        ];
    }

    public static function label(?string $status): string
    {
        return self::options()[$status ?? ''] ?? self::label(self::PENDING);
    }

    public static function badgeClass(?string $status): string
    {
        return match ($status) {
            self::IN_PROGRESS => 'text-bg-warning',
            self::PAUSED => 'text-bg-info',
            self::COMPLETED => 'text-bg-success',
            self::CANCELLED => 'text-bg-danger',
            self::REFUNDED => 'text-bg-dark',
            default => 'text-bg-secondary',
        };
    }

    public static function tone(?string $status): string
    {
        return match ($status) {
            self::IN_PROGRESS => 'warning',
            self::PAUSED => 'info',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
            self::REFUNDED => 'dark',
            default => 'secondary',
        };
    }

    public static function progressPercent(?string $status): int
    {
        return match ($status) {
            self::COMPLETED => 100,
            self::IN_PROGRESS, self::PAUSED => 50,
            default => 0,
        };
    }

    public static function isActive(?string $status): bool
    {
        return in_array($status, self::activeValues(), true);
    }

    public static function isClosed(?string $status): bool
    {
        return in_array($status, self::closedValues(), true);
    }

    public static function canBeClaimed(?string $status, ?int $boosterId = null): bool
    {
        return $boosterId === null && $status === self::PENDING;
    }

    public static function canBoosterOpen(?string $status): bool
    {
        return in_array($status, self::boosterWorkspaceValues(), true);
    }

    public static function canBoosterUpdate(?string $currentStatus, ?string $targetStatus): bool
    {
        return in_array($targetStatus, array_keys(self::boosterUpdateOptionsFor($currentStatus)), true);
    }

    public static function canBoosterDrop(?string $status): bool
    {
        return self::canBoosterOpen($status);
    }

    public static function adminTransitionTargets(?string $currentStatus): array
    {
        return match ($currentStatus) {
            self::PENDING => [self::IN_PROGRESS, self::PAUSED, self::COMPLETED, self::CANCELLED, self::REFUNDED],
            self::IN_PROGRESS => [self::PENDING, self::PAUSED, self::COMPLETED, self::CANCELLED, self::REFUNDED],
            self::PAUSED => [self::PENDING, self::IN_PROGRESS, self::COMPLETED, self::CANCELLED, self::REFUNDED],
            self::COMPLETED => [self::REFUNDED],
            self::CANCELLED => [self::REFUNDED],
            self::REFUNDED => [],
            default => self::values(),
        };
    }

    public static function canAdminTransition(?string $currentStatus, ?string $targetStatus): bool
    {
        if ($targetStatus === null || ! in_array($targetStatus, self::values(), true)) {
            return false;
        }

        if ($currentStatus === $targetStatus) {
            return true;
        }

        return in_array($targetStatus, self::adminTransitionTargets($currentStatus), true);
    }
}
