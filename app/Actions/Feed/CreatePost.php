<?php

namespace App\Actions\Feed;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class CreatePost
{
    /** @throws AuthorizationException */
    public function execute(User $user, Community $community, array $data): Post
    {
        if (! CommunityMember::where('community_id', $community->id)->where('user_id', $user->id)->exists()) {
            throw new AuthorizationException('You must be a member to post in this community.');
        }

        return Post::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'title'        => $data['title'] ?? null,
            'content'      => $data['content'],
            'image'        => $data['image'] ?? null,
            'video_url'    => $data['video_url'] ?? null,
            'is_pinned'    => false,
        ]);
    }
}
