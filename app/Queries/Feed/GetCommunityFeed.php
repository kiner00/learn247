<?php

namespace App\Queries\Feed;

use App\Models\Community;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class GetCommunityFeed
{
    public function paginated(Community $community, ?int $userId = null, int $perPage = 20): LengthAwarePaginator
    {
        $posts = $community->posts()
            ->with([
                'author:id,name,username,avatar',
                'likes',
                'comments' => fn ($q) => $q
                    ->whereNull('parent_id')
                    ->with([
                        'author:id,name,username,avatar',
                        'likes',
                        'replies' => fn ($r) => $r->with(['author:id,name,username,avatar', 'likes']),
                    ])
                    ->latest(),
            ])
            ->withCount('likes', 'comments')
            ->orderByDesc('is_pinned')
            ->latest()
            ->paginate($perPage);

        $posts->each(fn ($post) => $this->enrichPost($post, $userId));

        return $posts;
    }

    public function forShow(Community $community, ?int $userId = null, int $limit = 20): void
    {
        $community->load([
            'owner',
            'posts' => fn ($q) => $q
                ->with([
                    'author:id,name,avatar',
                    'likes',
                    'comments' => fn ($cq) => $cq
                        ->whereNull('parent_id')
                        ->with([
                            'author:id,name,avatar',
                            'likes',
                            'replies' => fn ($rq) => $rq->with(['author:id,name,avatar', 'likes']),
                        ])
                        ->latest(),
                ])
                ->withCount('likes', 'comments')
                ->orderByDesc('is_pinned')
                ->latest()
                ->take($limit),
        ]);
        $community->loadCount('members');

        $community->posts->each(fn ($post) => $this->enrichPost($post, $userId));

        $community->posts->each(function ($post) {
            $commenters = $post->comments
                ->sortByDesc('created_at')
                ->unique('user_id')
                ->take(4)
                ->map(fn ($c) => ['name' => $c->author?->name, 'avatar' => $c->author?->avatar])
                ->values();

            $post->commenter_avatars = $commenters;
            $post->last_comment_at   = $post->comments->max('created_at');
        });
    }

    public function enrichPost($post, ?int $userId): void
    {
        $post->reactions      = $this->reactionCounts($post->likes);
        $post->user_reaction  = $userId ? $post->likes->firstWhere('user_id', $userId)?->type : null;
        $post->user_has_liked = (bool) $post->user_reaction;
        $post->comments->each(function ($comment) use ($userId) {
            $comment->reactions      = $this->reactionCounts($comment->likes);
            $comment->user_reaction  = $userId ? $comment->likes->firstWhere('user_id', $userId)?->type : null;
            $comment->user_has_liked = (bool) $comment->user_reaction;
            $comment->likes_count    = $comment->likes->count();
            $comment->replies->each(function ($reply) use ($userId) {
                $reply->reactions      = $this->reactionCounts($reply->likes);
                $reply->user_reaction  = $userId ? $reply->likes->firstWhere('user_id', $userId)?->type : null;
                $reply->user_has_liked = (bool) $reply->user_reaction;
                $reply->likes_count    = $reply->likes->count();
            });
        });
    }

    public function reactionCounts(Collection $likes): array
    {
        $grouped = $likes->groupBy('type');
        return [
            'like'      => $grouped->get('like', collect())->count(),
            'handshake' => $grouped->get('handshake', collect())->count(),
            'trophy'    => $grouped->get('trophy', collect())->count(),
        ];
    }
}
