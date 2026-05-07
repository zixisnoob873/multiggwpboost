<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Services\Mail\AccountLifecycleEmailNotifier;
use App\Support\UserProfileData;

class StoreCustomerAction
{
    public function __construct(protected AccountLifecycleEmailNotifier $accountLifecycleEmailNotifier) {}

    public function execute(array $data): User
    {
        $user = new User;
        $user->forceFill(UserProfileData::payload($data, 'customer'))->save();
        $this->accountLifecycleEmailNotifier->queueAccountCreated($user);

        return $user;
    }
}
