<?php

namespace App\Policies;

use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function delete(User $user, Post $post): bool
    {
        if ($user->id === $post->user_id) {
            return true;
        }

        $member = CommunityMember::where('community_id', $post->community_id)
            ->where('user_id', $user->id)
            ->first();

        return $member && $member->canModerate();
    }
}
