<?php

namespace App\Actions\Notification;

use App\Models\Notification;
use App\Models\User;

class MarkAllAsRead
{
    public function execute(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
