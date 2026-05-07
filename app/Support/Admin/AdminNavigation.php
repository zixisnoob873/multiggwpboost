<?php

namespace App\Support\Admin;

use App\Models\User;
use App\Support\AdminPermission;

class AdminNavigation
{
    public function items(?User $user): array
    {
        if (! $user instanceof User || ! $user->isAdminUser()) {
            return [];
        }

        $items = [
            [
                'type' => 'link',
                'label' => 'Dashboard',
                'route' => 'admin-dashboard',
                'patterns' => ['admin-dashboard'],
            ],
            [
                'type' => 'group',
                'label' => 'Operations',
                'module' => 'operations',
                'items' => [
                    ['label' => 'Orders', 'route' => 'admin-total-order', 'patterns' => ['admin-total-order', 'admin-orders.*']],
                    ['label' => 'Chats', 'route' => 'admin-chats', 'patterns' => ['admin-chats', 'admin-chats.show']],
                    ['label' => 'Manual Orders', 'route' => 'admin-custom-order', 'patterns' => ['admin-custom-order', 'admin-orders.store-manual']],
                ],
            ],
            [
                'type' => 'group',
                'label' => 'People',
                'module' => 'people',
                'items' => [
                    ['label' => 'Customers', 'route' => 'admin-customers.index', 'patterns' => ['admin-customers.*']],
                    ['label' => 'Boosters', 'route' => 'admin-boosters.index', 'patterns' => ['admin-boosters.*']],
                    ['label' => 'Applications', 'route' => 'admin-booster-applications', 'patterns' => ['admin-booster-applications*']],
                    ['label' => 'Contact Inbox', 'route' => 'admin-contact-messages.index', 'patterns' => ['admin-contact-messages.*']],
                ],
            ],
            [
                'type' => 'group',
                'label' => 'Marketing',
                'module' => 'marketing',
                'items' => [
                    ['label' => 'Promotions', 'route' => 'admin-promotions.index', 'patterns' => ['admin-promotions.*']],
                    ['label' => 'Promo Codes', 'route' => 'admin-promo-codes.index', 'patterns' => ['admin-promo-codes.*']],
                    ['label' => 'Reviews', 'route' => 'admin-reviews.index', 'patterns' => ['admin-reviews.*']],
                    ['label' => 'Blog Articles', 'route' => 'admin-blog-articles.index', 'patterns' => ['admin-blog-articles.*']],
                ],
            ],
            [
                'type' => 'group',
                'label' => 'Content',
                'module' => 'content',
                'route' => 'admin-content.index',
                'items' => [
                    ['label' => 'Pages', 'route' => 'admin-pages.index', 'patterns' => ['admin-pages.*']],
                    ['label' => 'FAQs', 'route' => 'admin-content.faqs.index', 'patterns' => ['admin-content.faqs.index', 'admin-faqs.*']],
                    ['label' => 'Featured Boosters', 'route' => 'admin-content.featured-boosters.index', 'patterns' => ['admin-content.featured-boosters.index', 'admin-featured-boosters.*']],
                    ['label' => 'Addon Tooltips', 'route' => 'admin-content.addon-tooltips.index', 'patterns' => ['admin-content.addon-tooltips.index', 'admin-addon-settings.*']],
                ],
            ],
            [
                'type' => 'group',
                'label' => 'Finance',
                'module' => 'finance',
                'route' => 'admin-finance.index',
                'items' => [
                    ['label' => 'Withdrawals', 'route' => 'admin-withdrawal-requests.index', 'patterns' => ['admin-withdrawal-requests.*']],
                    ['label' => 'Wallet Adjustments', 'route' => 'admin-wallet-adjustments.index', 'patterns' => ['admin-wallet-adjustments.*']],
                    ['label' => 'Income Statement', 'route' => 'admin-income-statement', 'patterns' => ['admin-income-statement*']],
                ],
            ],
            [
                'type' => 'group',
                'label' => 'System',
                'module' => 'system',
                'items' => [
                    ['label' => 'Maintenance Mode', 'route' => 'admin-system.maintenance.index', 'patterns' => ['admin-system.maintenance.index', 'admin-maintenance-mode.*']],
                    ['label' => 'Pricing', 'route' => 'admin-pricing.index', 'patterns' => ['admin-pricing.*'], 'ability' => 'system.pricing.view'],
                    ['label' => 'Settings', 'route' => 'admin-system.settings', 'patterns' => ['admin-system.settings', 'admin-system.settings.update']],
                    ['label' => 'Audit Logs', 'route' => 'admin-system.audit-logs', 'patterns' => ['admin-system.audit-logs']],
                ],
            ],
        ];

        return array_values(array_filter(array_map(function (array $item) use ($user): ?array {
            if (($item['type'] ?? null) === 'link') {
                $item['active'] = $this->matches($item['patterns'] ?? []);

                return $item;
            }

            $module = $item['module'] ?? null;
            if (! is_string($module) || ! $user->canAccessAdminModule($module)) {
                return null;
            }

            $item['items'] = array_values(array_filter(array_map(function (array $child) use ($user): ?array {
                $ability = $child['ability'] ?? null;
                if (is_string($ability) && ! AdminPermission::userCan($user, $ability)) {
                    return null;
                }

                $child['active'] = $this->matches($child['patterns'] ?? []);

                return $child;
            }, $item['items'] ?? [])));

            if ($item['items'] === []) {
                return null;
            }

            $item['active'] = collect($item['items'])->contains(fn (array $child): bool => (bool) ($child['active'] ?? false));

            return $item;
        }, $items)));
    }

    protected function matches(array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (request()->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }
}
