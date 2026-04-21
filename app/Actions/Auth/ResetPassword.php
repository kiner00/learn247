<?php

namespace App\Actions\Auth;

use Illuminate\Support\Facades\Password;

class ResetPassword
{
    public function execute(array $credentials): string
    {
        return Password::reset(
            $credentials,
            function ($user, $password) {
                $user->forceFill([
                    'password' => bcrypt($password),
                    'needs_password_setup' => false,
                ])->save();
            }
        );
    }
}
