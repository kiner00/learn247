<?php

namespace App\Actions\Feed;

use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class TogglePin
{
    /** @throws AuthorizationException */
    public function execute(User $user, Post $post): Post
    {
        $community  = $post->community;
        $membership = $community->members()->where('user_id', $user->id)->first();
        $isAdmin    = $community->owner_id === $user->id || $membership?->role === 'admin';

        if (! $isAdmin) {
            throw new AuthorizationException('Only admins can pin or unpin posts.');
        }

        $post->update(['is_pinned' => ! $post->is_pinned]);

        return $post->refresh();
    }
}
