<?php

namespace App\Policies;

use App\Models\BoosterApplication;
use App\Models\User;

class BoosterApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageApplications($user);
    }

    public function view(User $user, BoosterApplication $boosterApplication): bool
    {
        return $this->canManageApplications($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageApplications($user);
    }

    public function update(User $user, BoosterApplication $boosterApplication): bool
    {
        return $this->canManageApplications($user);
    }

    public function convert(User $user, BoosterApplication $boosterApplication): bool
    {
        return $this->canManageApplications($user);
    }

    protected function canManageApplications(User $user): bool
    {
        return $user->isAdminUser() && $user->canAccessAdminModule('people');
    }
}
