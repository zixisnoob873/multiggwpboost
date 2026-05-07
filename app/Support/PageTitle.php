<?php

namespace App\Support;

use App\Models\Order;
use App\Models\PromoCode;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

class PageTitle
{
    public const BRAND = 'GGWP-Boost';

    public static function resolve(?string $fallbackTitle = null, ?Route $route = null): string
    {
        $route ??= request()->route();

        $label = self::mappedLabel($route)
            ?? self::normalizedLabel($fallbackTitle)
            ?? self::inferredLabel($route)
            ?? 'Home';

        return self::format($label);
    }

    public static function format(?string $label = null): string
    {
        $normalized = self::normalizedLabel($label);

        if ($normalized === null) {
            return self::BRAND;
        }

        return "{$normalized} | ".self::BRAND;
    }

    protected static function mappedLabel(?Route $route): ?string
    {
        $routeName = $route?->getName();

        return match ($routeName) {
            'home' => 'VALORANT Rank Boosting',
            'login' => 'Login',
            'password.request' => 'Reset Password',
            'password.reset' => 'Choose a New Password',
            'signup' => 'Create Account',
            'oauth.complete-profile' => 'Complete Profile',
            'contact' => 'VALORANT Boosting Support',
            'faq' => 'VALORANT Boosting FAQ',
            'checkout' => 'VALORANT Boost Pricing',
            'become-booster' => 'Become a VALORANT Booster',
            'code-of-ethics' => 'Code of Ethics',
            'privacy-policy' => 'Privacy Policy',
            'refund-policy' => 'Refund Policy',
            'reviews' => 'VALORANT Boosting Reviews',
            'terms-and-conditions' => 'Terms and Conditions',
            'customer-dashboard' => 'Customer Dashboard',
            'customer-upgrade-order' => 'Extend / Upgrade Boost',
            'user-chats' => 'Order Workspace',
            'user-chats.show' => self::orderLabel($route?->parameter('order'), 'Order'),
            'my-order' => 'Order Workspace Redirect',
            'allorders' => 'Order History',
            'booster-dashboard' => 'Booster Dashboard',
            'booster-orders' => 'Booster Orders',
            'booster-claim-orders' => 'Claim Orders',
            'booster-wallet' => 'Booster Wallet',
            'booster-chats' => 'Booster Chats',
            'booster-chats.show' => self::orderLabel($route?->parameter('order'), 'Booster Order'),
            'admin-dashboard' => 'Admin Dashboard',
            'admin-content.index' => 'Content Management',
            'admin-contact-messages.index' => 'Contact Inbox',
            'admin-pages.index' => 'Pages',
            'admin-pages.edit' => 'Edit Page',
            'admin-reviews.index' => 'Reviews',
            'admin-reviews.create' => 'Add Review',
            'admin-reviews.edit' => 'Edit Review',
            'admin-blog-articles.index' => 'Blog Articles',
            'admin-blog-articles.create' => 'Create Blog Article',
            'admin-blog-articles.edit' => 'Edit Blog Article',
            'admin-booster-applications' => 'Booster Applications',
            'admin-withdrawal-requests.index' => 'Withdrawal Requests',
            'admin-income-statement' => 'Income Statement',
            'admin-chats' => 'Admin Chats',
            'admin-chats.show' => self::orderLabel($route?->parameter('order'), 'Admin Chat Order'),
            'admin-custom-order' => 'Custom Orders',
            'admin-promotions.index' => 'Promotions',
            'admin-promotions.edit' => self::promotionLabel($route?->parameter('promotion'), 'Edit Promotion'),
            'admin-promo-codes.index' => 'Promo Codes',
            'admin-promo-codes.details' => self::promoCodeLabel($route?->parameter('promoCode')),
            'admin-promo-codes.edit' => self::promoCodeLabel($route?->parameter('promoCode'), 'Edit Promo Code'),
            'admin-boosters.index' => 'Booster Management',
            'admin-boosters.create' => 'Add Booster',
            'admin-boosters.show' => self::userLabel($route?->parameter('booster'), 'Booster Profile'),
            'admin-boosters.edit' => self::userLabel($route?->parameter('booster'), 'Edit Booster'),
            'admin-customers.index' => 'Customer Management',
            'admin-customers.create' => 'Add Customer',
            'admin-customers.show' => self::userLabel($route?->parameter('user'), 'Customer Profile'),
            'admin-customers.edit' => self::userLabel($route?->parameter('user'), 'Edit Customer'),
            'admin-total-order' => 'Total Orders',
            'admin-orders.edit' => self::orderLabel($route?->parameter('order'), 'Edit Order'),
            default => null,
        };
    }

    protected static function inferredLabel(?Route $route): ?string
    {
        $routeName = $route?->getName();

        if (! is_string($routeName) || trim($routeName) === '') {
            return null;
        }

        $lastSegment = Str::afterLast($routeName, '.');
        $base = str_replace(['-', '_'], ' ', $lastSegment);

        return self::normalizedLabel(Str::title($base));
    }

    protected static function normalizedLabel(?string $label): ?string
    {
        $value = trim(html_entity_decode(strip_tags((string) $label), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($value === '') {
            return null;
        }

        $patterns = [
            '/^\s*ggwp(?:[\s-]*boost)?\s*[|\-:]\s*/i',
            '/\s*[|\-:]\s*ggwp(?:[\s-]*boost)?\s*$/i',
        ];

        foreach ($patterns as $pattern) {
            $value = preg_replace($pattern, '', $value) ?? $value;
        }

        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        if ($value === '' || strcasecmp($value, self::BRAND) === 0 || strcasecmp($value, 'GGWP Boost') === 0) {
            return null;
        }

        return $value;
    }

    protected static function orderLabel(mixed $orderValue, string $prefix): string
    {
        $orderNumber = null;

        if ($orderValue instanceof Order) {
            $orderNumber = $orderValue->order_number ?: (string) $orderValue->getKey();
        } elseif ($orderValue !== null && $orderValue !== '') {
            $orderNumber = (string) $orderValue;
        }

        return $orderNumber
            ? sprintf('%s #%s', $prefix, $orderNumber)
            : $prefix;
    }

    protected static function userLabel(mixed $userValue, string $prefix): string
    {
        $name = null;

        if ($userValue instanceof User) {
            $name = trim((string) ($userValue->nickname ?: $userValue->name ?: trim(($userValue->first_name ?? '').' '.($userValue->last_name ?? ''))));
        } elseif ($userValue !== null && $userValue !== '') {
            $name = (string) $userValue;
        }

        return $name ? sprintf('%s %s', $prefix, $name) : $prefix;
    }

    protected static function promoCodeLabel(mixed $promoCodeValue, string $prefix = 'Promo Code'): string
    {
        $code = null;

        if ($promoCodeValue instanceof PromoCode) {
            $code = trim((string) $promoCodeValue->code);
        } elseif ($promoCodeValue !== null && $promoCodeValue !== '') {
            $code = (string) $promoCodeValue;
        }

        return $code ? sprintf('%s %s', $prefix, $code) : $prefix;
    }

    protected static function promotionLabel(mixed $promotionValue, string $prefix = 'Promotion'): string
    {
        $title = null;

        if ($promotionValue instanceof Promotion) {
            $title = trim((string) $promotionValue->title);
        } elseif ($promotionValue !== null && $promotionValue !== '') {
            $title = (string) $promotionValue;
        }

        return $title ? sprintf('%s %s', $prefix, $title) : $prefix;
    }
}
