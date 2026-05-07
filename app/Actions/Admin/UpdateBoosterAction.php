<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Services\Mail\AccountLifecycleEmailNotifier;
use App\Support\UserProfileData;

class UpdateBoosterAction
{
    public function __construct(protected AccountLifecycleEmailNotifier $accountLifecycleEmailNotifier) {}

    public function execute(User $user, array $data): User
    {
        $previousStatus = $user->account_status;
        $payload = UserProfileData::payload($data, 'booster', ! empty($data['password']));

        $user->forceFill($payload)->save();
        $this->accountLifecycleEmailNotifier->queueStatusChanged($user->fresh(), $previousStatus);

        return $user->fresh();
    }
}
