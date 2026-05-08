<?php

namespace App\Support;

use App\Models\User;

class AdminPermission
{
    public const SUPER_ADMIN = User::ROLE_SUPER_ADMIN;

    /**
     * @var array<int, string>
     */
    protected const ABILITIES = [
        'dashboard.view',

        'operations.orders.view',
        'operations.orders.manage',
        'operations.orders.export',
        'operations.chats.view',
        'operations.chats.manage',
        'operations.manual_orders.view',
        'operations.manual_orders.manage',

        'people.customers.view',
        'people.customers.manage',
        'people.boosters.view',
        'people.boosters.manage',
        'people.applications.view',
        'people.applications.manage',
        'people.inbox.view',
        'people.inbox.manage',

        'marketing.promotions.view',
        'marketing.promotions.manage',
        'marketing.promo_codes.view',
        'marketing.promo_codes.manage',
        'marketing.reviews.view',
        'marketing.reviews.manage',
        'marketing.blog.view',
        'marketing.blog.manage',

        'content.hub.view',
        'content.pages.view',
        'content.pages.manage',
        'content.faqs.view',
        'content.faqs.manage',
        'content.featured_boosters.view',
        'content.featured_boosters.manage',
        'content.addon_tooltips.view',
        'content.addon_tooltips.manage',

        'marketplace.catalog.view',
        'marketplace.catalog.manage',
        'marketplace.pricing.manage',

        'finance.overview.view',
        'finance.withdrawals.view',
        'finance.withdrawals.manage',
        'finance.wallet_adjustments.manage',
        'finance.income_statement.view',
        'finance.income_statement.export',

        'system.maintenance.manage',
        'system.pricing.view',
        'system.pricing.manage',
        'system.settings.view',
        'system.settings.manage',
        'system.audit.view',
    ];

    /**
     * @return array<int, string>
     */
    public static function roles(): array
    {
        return [self::SUPER_ADMIN];
    }

    /**
     * @return array<string, string>
     */
    public static function roleOptions(): array
    {
        return [
            self::SUPER_ADMIN => 'Super Admin',
        ];
    }

    public static function roleLabel(?string $role): string
    {
        return 'Super Admin';
    }

    public static function adminRole(?User $user): ?string
    {
        return self::isAdmin($user) ? self::SUPER_ADMIN : null;
    }

    public static function isAdmin(?User $user): bool
    {
        return $user instanceof User && $user->isAdminUser();
    }

    public static function userCan(?User $user, string $ability): bool
    {
        return self::isAdmin($user) && in_array($ability, self::ABILITIES, true);
    }

    public static function roleAllows(string $role, string $ability): bool
    {
        return $role === self::SUPER_ADMIN && in_array($ability, self::ABILITIES, true);
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return self::ABILITIES;
    }
}
