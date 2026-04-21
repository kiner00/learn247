<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\EmailVerificationToken;
use App\Support\InvalidEmailVerificationToken;
use Illuminate\Auth\Events\Verified;
use Illuminate\Validation\ValidationException;

class VerifyEmail
{
    public function execute(string $token): User
    {
        try {
            $payload = EmailVerificationToken::parse($token);
        } catch (InvalidEmailVerificationToken $e) {
            throw ValidationException::withMessages(['token' => $e->getMessage()]);
        }

        $user = User::find($payload['id']);

        if (! $user || $user->email !== $payload['email']) {
            throw ValidationException::withMessages([
                'token' => 'This verification link is no longer valid.',
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return $user->refresh();
    }
}
