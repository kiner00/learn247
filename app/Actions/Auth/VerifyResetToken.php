<?php

namespace App\Actions\Auth;

use Illuminate\Support\Facades\Password;

class VerifyResetToken
{
    public function execute(string $email, string $token): bool
    {
        $broker = Password::broker();
        $user = $broker->getUser(['email' => $email]);

        if (! $user) {
            return false;
        }

        return $broker->tokenExists($user, $token);
    }
}
