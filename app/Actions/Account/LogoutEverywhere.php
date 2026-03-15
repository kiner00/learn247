<?php

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Support\Str;

class LogoutEverywhere
{
    public function execute(User $user): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }
}
