<?php

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CancelAccountDeletion
{
    public function execute(string $email, string $password): User
    {
        $user = User::withTrashed()->where('email', $email)->first();

        // Single generic message for every failure mode so we don't leak
        // which accounts exist / are soft-deleted.
        $genericFailure = fn () => ValidationException::withMessages([
            'email' => 'No pending deletion found for these credentials, or the grace period has expired.',
        ]);

        if (! $user || ! Hash::check($password, $user->password)) {
            throw $genericFailure();
        }

        if (! $user->trashed()) {
            throw $genericFailure();
        }

        $deletedAt = $user->deleted_at;
        if ($deletedAt === null || $deletedAt->diffInDays(now()) >= User::DELETION_GRACE_DAYS) {
            throw $genericFailure();
        }

        $user->restore();

        return $user->fresh();
    }
}
