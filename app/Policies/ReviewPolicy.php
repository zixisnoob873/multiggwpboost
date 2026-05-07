<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageReviews($user);
    }

    public function view(User $user, Review $review): bool
    {
        return $this->canManageReviews($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageReviews($user);
    }

    public function update(User $user, Review $review): bool
    {
        return $this->canManageReviews($user);
    }

    public function delete(User $user, Review $review): bool
    {
        return $this->canManageReviews($user);
    }

    protected function canManageReviews(User $user): bool
    {
        return $user->isAdminUser() && $user->canAccessAdminModule('marketing');
    }
}
