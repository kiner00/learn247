<?php

namespace App\Actions\Account;

use App\Models\User;

class UpdateChatPrefs
{
    public function execute(User $user, array $prefs): void
    {
        $user->update(['chat_prefs' => $prefs]);
    }
}
