<?php

namespace App\Actions\Account;

use App\Models\User;

class UpdatePayout
{
    public function execute(User $user, array $data): void
    {
        $user->update($data);
    }
}
