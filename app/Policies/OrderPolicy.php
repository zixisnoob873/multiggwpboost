<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canAccessOrderAdmin($user);
    }

    public function view(User $user, Order $order): bool
    {
        return $this->canAccessOrderAdmin($user)
            || $order->user_id === $user->getKey()
            || $order->booster_id === $user->getKey();
    }

    public function create(User $user): bool
    {
        return $this->canAccessOrderAdmin($user) || User::normalizeRole($user->role) === User::ROLE_CUSTOMER;
    }

    public function update(User $user, Order $order): bool
    {
        return $this->canManageOrder($user)
            || $order->user_id === $user->getKey()
            || $order->booster_id === $user->getKey();
    }

    public function updateStatus(User $user, Order $order): bool
    {
        return $this->canManageOrder($user) || $order->booster_id === $user->getKey();
    }

    public function assignBooster(User $user, Order $order): bool
    {
        return $this->canManageOrder($user);
    }

    public function downloadCompletionProof(User $user, Order $order): bool
    {
        return $this->canManageOrder($user) || $order->booster_id === $user->getKey();
    }

    protected function canAccessOrderAdmin(User $user): bool
    {
        return $user->isAdminUser() && (
            $user->canAccessAdminModule('operations')
            || $user->canAccessAdminModule('finance')
            || $user->canAccessAdminModule('dashboard')
        );
    }

    protected function canManageOrder(User $user): bool
    {
        return $user->isAdminUser() && $user->canAccessAdminModule('operations');
    }
}
