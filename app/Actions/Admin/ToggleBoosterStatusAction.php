<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Services\Mail\AccountLifecycleEmailNotifier;

class ToggleBoosterStatusAction
{
    public function __construct(protected AccountLifecycleEmailNotifier $accountLifecycleEmailNotifier) {}

    public function execute(User $user): User
    {
        $previousStatus = $user->account_status;
        $user->forceFill([
            'account_status' => $previousStatus === 'suspended' ? 'active' : 'suspended',
        ])->save();
        $user = $user->fresh();
        $this->accountLifecycleEmailNotifier->queueStatusChanged($user, $previousStatus);

        return $user;
    }
}
