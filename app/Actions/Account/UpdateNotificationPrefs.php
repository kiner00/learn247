<?php

namespace App\Actions\Account;

use App\Models\User;

class UpdateNotificationPrefs
{
    public function execute(User $user, array $prefs): void
    {
        $user->update(['notification_prefs' => $prefs]);
    }
}
