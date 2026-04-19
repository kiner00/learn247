<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SetPassword
{
    public function execute(User $user, string $password): void
    {
        $user->forceFill([
            'password' => Hash::make($password),
            'needs_password_setup' => false,
        ])->save();
    }
}
