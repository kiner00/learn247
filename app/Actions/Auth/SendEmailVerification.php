<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class SendEmailVerification
{
    public function execute(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => 'Your email is already verified.',
            ]);
        }

        $user->sendEmailVerificationNotification();
    }
}
