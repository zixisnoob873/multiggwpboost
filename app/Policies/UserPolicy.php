<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManagePeople($user) || $user->canAccessAdminModule('finance') || $user->canAccessAdminModule('operations');
    }

    public function view(User $user, User $subject): bool
    {
        return $user->getKey() === $subject->getKey() || $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->canManagePeople($user);
    }

    public function update(User $user, User $subject): bool
    {
        return $user->getKey() === $subject->getKey() || $this->canManagePeople($user);
    }

    public function delete(User $user, User $subject): bool
    {
        return $this->canManagePeople($user) && $user->getKey() !== $subject->getKey();
    }

    protected function canManagePeople(User $user): bool
    {
        return $user->isAdminUser() && $user->canAccessAdminModule('people');
    }
}
