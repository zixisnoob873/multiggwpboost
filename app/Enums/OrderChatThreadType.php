<?php

namespace App\Enums;

use App\Models\User;

enum OrderChatThreadType: string
{
    case CUSTOMER_BOOSTER = 'customer_booster';
    case CUSTOMER_ADMIN = 'customer_admin';
    case BOOSTER_ADMIN = 'booster_admin';

    public static function values(): array
    {
        return array_map(static fn (self $type) => $type->value, self::cases());
    }

    public function includesCustomer(): bool
    {
        return match ($this) {
            self::CUSTOMER_BOOSTER, self::CUSTOMER_ADMIN => true,
            self::BOOSTER_ADMIN => false,
        };
    }

    public function includesBooster(): bool
    {
        return match ($this) {
            self::CUSTOMER_BOOSTER, self::BOOSTER_ADMIN => true,
            self::CUSTOMER_ADMIN => false,
        };
    }

    public function includesAdmin(): bool
    {
        return match ($this) {
            self::CUSTOMER_ADMIN, self::BOOSTER_ADMIN => true,
            self::CUSTOMER_BOOSTER => false,
        };
    }

    public static function visibleForRole(string $viewerRole): array
    {
        return match (User::normalizeRole($viewerRole)) {
            'customer' => [self::CUSTOMER_BOOSTER, self::CUSTOMER_ADMIN],
            'booster' => [self::CUSTOMER_BOOSTER, self::BOOSTER_ADMIN],
            User::ROLE_SUPER_ADMIN => [self::BOOSTER_ADMIN, self::CUSTOMER_ADMIN, self::CUSTOMER_BOOSTER],
            default => [],
        };
    }

    public function participantsLabel(): string
    {
        return match ($this) {
            self::CUSTOMER_ADMIN => 'Customer / Admin',
            self::BOOSTER_ADMIN => 'Booster / Admin',
            self::CUSTOMER_BOOSTER => 'Order Chat',
        };
    }

    public function buttonLabelForRole(string $viewerRole): string
    {
        return match ($viewerRole) {
            'customer' => match ($this) {
                self::CUSTOMER_BOOSTER => 'Booster',
                self::CUSTOMER_ADMIN => 'Admin',
                self::BOOSTER_ADMIN => $this->participantsLabel(),
            },
            'booster' => match ($this) {
                self::CUSTOMER_BOOSTER => 'Customer',
                self::BOOSTER_ADMIN => 'Admin',
                self::CUSTOMER_ADMIN => $this->participantsLabel(),
            },
            default => match ($this) {
                self::CUSTOMER_ADMIN => 'Customer',
                self::BOOSTER_ADMIN => 'Booster',
                self::CUSTOMER_BOOSTER => 'Order Chat',
            },
        };
    }

    public function titleLabelForRole(string $viewerRole): string
    {
        return $this->buttonLabelForRole($viewerRole);
    }

    public function hintForRole(string $viewerRole): string
    {
        return '';
    }

    public function emptyTitleForRole(string $viewerRole): string
    {
        return match ($viewerRole) {
            'customer' => match ($this) {
                self::CUSTOMER_BOOSTER => 'No Conversation between You and Booster yet.',
                self::CUSTOMER_ADMIN => 'No Conversation between You and Admin yet.',
                self::BOOSTER_ADMIN => 'No Conversation yet.',
            },
            'booster' => match ($this) {
                self::CUSTOMER_BOOSTER => 'No Conversation between You and Customer yet.',
                self::BOOSTER_ADMIN => 'No Conversation between You and Admin yet.',
                self::CUSTOMER_ADMIN => 'No Conversation yet.',
            },
            default => match ($this) {
                self::CUSTOMER_ADMIN => 'No Conversation between Admin and Customer yet.',
                self::BOOSTER_ADMIN => 'No Conversation between Admin and Booster yet.',
                self::CUSTOMER_BOOSTER => 'No Conversation between Customer and Booster yet.',
            },
        };
    }

    public function emptyCopyForRole(string $viewerRole): string
    {
        return '';
    }

    public function stateLabelForRole(string $viewerRole): string
    {
        if (User::normalizeRole($viewerRole) === User::ROLE_SUPER_ADMIN && $this === self::CUSTOMER_BOOSTER) {
            return 'Monitored';
        }

        return 'Live';
    }

    public function canSendForRole(string $viewerRole): bool
    {
        return match (User::normalizeRole($viewerRole)) {
            'customer' => $this->includesCustomer(),
            'booster' => $this->includesBooster(),
            User::ROLE_SUPER_ADMIN => $this->includesAdmin(),
            default => false,
        };
    }
}
