<?php

namespace App\Policies;

use App\Models\ContactMessage;
use App\Models\User;

class ContactMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageMessages($user);
    }

    public function view(User $user, ContactMessage $contactMessage): bool
    {
        return $this->canManageMessages($user);
    }

    public function update(User $user, ContactMessage $contactMessage): bool
    {
        return $this->canManageMessages($user);
    }

    public function delete(User $user, ContactMessage $contactMessage): bool
    {
        return $this->canManageMessages($user);
    }

    protected function canManageMessages(User $user): bool
    {
        return $user->isAdminUser() && $user->canAccessAdminModule('people');
    }
}
