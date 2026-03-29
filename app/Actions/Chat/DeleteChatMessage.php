<?php

namespace App\Actions\Chat;

use App\Events\ChatMessageDeleted;
use App\Models\Community;
use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class DeleteChatMessage
{
    /** @throws AuthorizationException */
    public function execute(User $user, Community $community, Message $message): void
    {
        if ($message->community_id !== $community->id) {
            throw new AuthorizationException('Message does not belong to this community.');
        }

        if ($message->user_id !== $user->id && ! $user->is_super_admin) {
            throw new AuthorizationException('You can only delete your own messages.');
        }

        $communityId = $message->community_id;
        $messageId   = $message->id;

        $message->delete();

        ChatMessageDeleted::dispatch($communityId, $messageId);
    }
}
