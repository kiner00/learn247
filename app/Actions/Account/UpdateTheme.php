<?php

namespace App\Actions\Account;

use App\Models\User;

class UpdateTheme
{
    public function execute(User $user, string $theme): void
    {
        $user->update(['theme' => $theme]);
    }
}
