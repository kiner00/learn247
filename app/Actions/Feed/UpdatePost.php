<?php

namespace App\Actions\Feed;

use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class UpdatePost
{
    /** @throws AuthorizationException */
    public function execute(User $user, Post $post, array $data): Post
    {
        if ($user->id !== $post->user_id) {
            throw new AuthorizationException('You can only edit your own posts.');
        }

        $post->update($data);

        return $post;
    }
}
