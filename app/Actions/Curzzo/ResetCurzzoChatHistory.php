<?php

namespace App\Actions\Curzzo;

use App\Models\Curzzo;
use App\Models\CurzzoMessage;
use App\Models\User;

class ResetCurzzoChatHistory
{
    public function execute(User $user, Curzzo $curzzo): void
    {
        CurzzoMessage::where('curzzo_id', $curzzo->id)
            ->where('user_id', $user->id)
            ->delete();
    }
}
