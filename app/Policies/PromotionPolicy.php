<?php

namespace App\Policies;

use App\Models\Promotion;
use App\Models\User;

class PromotionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManagePromotions($user);
    }

    public function view(User $user, Promotion $promotion): bool
    {
        return $this->canManagePromotions($user);
    }

    public function create(User $user): bool
    {
        return $this->canManagePromotions($user);
    }

    public function update(User $user, Promotion $promotion): bool
    {
        return $this->canManagePromotions($user);
    }

    public function delete(User $user, Promotion $promotion): bool
    {
        return $this->canManagePromotions($user);
    }

    protected function canManagePromotions(User $user): bool
    {
        return $user->isAdminUser() && $user->canAccessAdminModule('marketing');
    }
}
