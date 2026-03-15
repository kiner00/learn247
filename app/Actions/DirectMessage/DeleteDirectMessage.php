<?php

namespace App\Actions\DirectMessage;

use App\Models\DirectMessage;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class DeleteDirectMessage
{
    /** @throws AuthorizationException */
    public function execute(User $user, DirectMessage $message): void
    {
        if ($message->sender_id !== $user->id) {
            throw new AuthorizationException('You can only delete your own messages.');
        }

        $message->delete();
    }
}
