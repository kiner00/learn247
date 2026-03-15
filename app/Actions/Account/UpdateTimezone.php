<?php

namespace App\Actions\Account;

use App\Models\User;

class UpdateTimezone
{
    public function execute(User $user, string $timezone): void
    {
        $user->update(['timezone' => $timezone]);
    }
}
