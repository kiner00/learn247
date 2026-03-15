<?php

namespace App\Actions\Account;

use App\Models\User;

class UpdateEmail
{
    public function execute(User $user, string $email): void
    {
        $user->update(['email' => $email]);
    }
}
