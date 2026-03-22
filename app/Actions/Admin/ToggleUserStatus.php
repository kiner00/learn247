<?php

namespace App\Actions\Admin;

use App\Models\User;

class ToggleUserStatus
{
    public function execute(User $user): void
    {
        abort_if($user->is_super_admin, 422, 'Cannot disable a super admin.');

        $user->update(['is_active' => ! $user->is_active]);
    }
}
