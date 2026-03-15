<?php

namespace App\Actions\Account;

use App\Models\User;

class UpdateApiProfile
{
    public function execute(User $user, array $data): User
    {
        $user->update($data);

        return $user->refresh();
    }
}
