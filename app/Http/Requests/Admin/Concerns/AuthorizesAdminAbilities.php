<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Support\AdminPermission;

trait AuthorizesAdminAbilities
{
    protected function authorizeAdminAbility(string $ability): bool
    {
        return AdminPermission::userCan($this->user(), $ability);
    }
}
