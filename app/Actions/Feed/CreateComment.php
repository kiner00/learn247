<?php

namespace App\Actions\Feed;

use App\Models\Comment;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class CreateComment
{
    /** @throws AuthorizationException */
    public function execute(User $user, Post $post, array $data): Comment
    {
        if (! CommunityMember::where('community_id', $post->community_id)->where('user_id', $user->id)->exists()) {
            throw new AuthorizationException('You must be a member to comment in this community.');
        }

        return Comment::create([
            'post_id'      => $post->id,
            'community_id' => $post->community_id,
            'user_id'      => $user->id,
            'parent_id'    => $data['parent_id'] ?? null,
            'content'      => $data['content'],
        ]);
    }
}
