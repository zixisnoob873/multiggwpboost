<?php

namespace App\Policies;

use App\Models\BlogArticle;
use App\Models\User;

class BlogArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageMarketing($user);
    }

    public function view(User $user, BlogArticle $blogArticle): bool
    {
        return $this->canManageMarketing($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageMarketing($user);
    }

    public function update(User $user, BlogArticle $blogArticle): bool
    {
        return $this->canManageMarketing($user);
    }

    public function delete(User $user, BlogArticle $blogArticle): bool
    {
        return $this->canManageMarketing($user);
    }

    public function publish(User $user, BlogArticle $blogArticle): bool
    {
        return $this->canManageMarketing($user);
    }

    public function unpublish(User $user, BlogArticle $blogArticle): bool
    {
        return $this->canManageMarketing($user);
    }

    protected function canManageMarketing(User $user): bool
    {
        return $user->isAdminUser() && $user->canAccessAdminModule('marketing');
    }
}
