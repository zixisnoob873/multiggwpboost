<?php

namespace App\Services\Chat;

use App\Enums\OrderChatThreadType;
use App\Models\Order;
use App\Models\User;

class OrderChatAuthorizationService
{
    public function authorizeViewThread(User $user, Order $order, OrderChatThreadType $threadType): void
    {
        abort_unless($this->canViewThread($user, $order, $threadType), 403);
    }

    public function authorizeSendToThread(User $user, Order $order, OrderChatThreadType $threadType): void
    {
        abort_unless($this->canSendToThread($user, $order, $threadType), 403);
    }

    public function canViewThread(User $user, Order $order, OrderChatThreadType $threadType): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        $role = User::normalizeRole($user->role);

        if ($role === User::ROLE_CUSTOMER) {
            return (int) $order->user_id === (int) $user->getKey() && $threadType->includesCustomer();
        }

        if ($role === User::ROLE_BOOSTER) {
            return (int) $order->booster_id === (int) $user->getKey()
                && $order->canBoosterOpenWorkspace()
                && $threadType->includesBooster();
        }

        return false;
    }

    public function canSendToThread(User $user, Order $order, OrderChatThreadType $threadType): bool
    {
        if (! $this->canViewThread($user, $order, $threadType)) {
            return false;
        }

        return match (User::normalizeRole($user->role)) {
            User::ROLE_SUPER_ADMIN => $threadType->includesAdmin(),
            User::ROLE_CUSTOMER => $threadType->includesCustomer(),
            User::ROLE_BOOSTER => $threadType->includesBooster(),
            default => false,
        };
    }

    public function canSubscribe(User $user, int $orderId, string $threadType): bool
    {
        $type = OrderChatThreadType::tryFrom($threadType);

        if (! $type) {
            return false;
        }

        $order = Order::query()
            ->select(['id', 'user_id', 'booster_id', 'status'])
            ->find($orderId);

        if (! $order) {
            return false;
        }

        return $this->canViewThread($user, $order, $type);
    }

    protected function isAdmin(User $user): bool
    {
        return $user->isAdminUser();
    }
}
