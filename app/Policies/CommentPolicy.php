<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\CommunityMember;
use App\Models\User;

class CommentPolicy
{
    public function delete(User $user, Comment $comment): bool
    {
        if ($user->id === $comment->user_id) {
            return true;
        }

        $member = CommunityMember::where('community_id', $comment->community_id)
            ->where('user_id', $user->id)
            ->first();

        return $member && $member->canModerate();
    }
}
