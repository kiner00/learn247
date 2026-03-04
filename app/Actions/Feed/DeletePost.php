<?php

namespace App\Actions\Feed;

use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class DeletePost
{
    /** @throws AuthorizationException */
    public function execute(User $user, Post $post): void
    {
        if ($post->user_id !== $user->id) {
            $member = CommunityMember::where('community_id', $post->community_id)
                ->where('user_id', $user->id)
                ->first();

            if (! $member || ! $member->canModerate()) {
                throw new AuthorizationException('You cannot delete this post.');
            }
        }

        $post->delete();
    }
}
