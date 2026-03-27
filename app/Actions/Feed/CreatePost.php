<?php

namespace App\Actions\Feed;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Notification;
use App\Models\Post;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;

class CreatePost
{
    public function __construct(private StorageService $storage) {}

    /** @throws AuthorizationException */
    public function execute(User $user, Community $community, array $data): Post
    {
        $member = CommunityMember::where('community_id', $community->id)->where('user_id', $user->id)->first();

        if (! $member) {
            throw new AuthorizationException('You must be a member to post in this community.');
        }

        if ($member->is_blocked) {
            throw new AuthorizationException('You have been blocked from posting in this community.');
        }

        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            $data['image'] = $this->storage->upload($data['image'], 'post-images');
        }

        $post = Post::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'title'        => $data['title'] ?? null,
            'content'      => $data['content'],
            'image'        => $data['image'] ?? null,
            'video_url'    => $data['video_url'] ?? null,
            'is_pinned'    => false,
        ]);

        if ($community->owner_id !== $user->id) {
            Notification::create([
                'user_id'      => $community->owner_id,
                'actor_id'     => $user->id,
                'community_id' => $community->id,
                'type'         => 'new_post',
                'data'         => [
                    'post_title' => $post->title ?? 'New post',
                    'message'    => "{$user->name} posted in {$community->name}",
                ],
            ]);
        }

        return $post;
    }
}
