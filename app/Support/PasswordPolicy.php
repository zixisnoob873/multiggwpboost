<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

class PasswordPolicy
{
    public static function rule(): Password
    {
        $rule = Password::min(8)
            ->numbers();

        return $rule;
    }
}
