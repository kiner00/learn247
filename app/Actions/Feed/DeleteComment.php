<?php

namespace App\Actions\Feed;

use App\Models\Comment;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class DeleteComment
{
    /** @throws AuthorizationException */
    public function execute(User $user, Comment $comment): void
    {
        if ($comment->user_id !== $user->id) {
            $member = CommunityMember::where('community_id', $comment->community_id)
                ->where('user_id', $user->id)
                ->first();

            if (! $member || ! $member->canModerate()) {
                throw new AuthorizationException('You cannot delete this comment.');
            }
        }

        $comment->delete();
    }
}
