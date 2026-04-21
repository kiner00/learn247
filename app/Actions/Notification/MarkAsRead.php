<?php

namespace App\Actions\Notification;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class MarkAsRead
{
    /** @throws AuthorizationException */
    public function execute(User $user, Notification $notification): Notification
    {
        if ($notification->user_id !== $user->id) {
            throw new AuthorizationException('You can only mark your own notifications as read.');
        }

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return $notification;
    }
}
