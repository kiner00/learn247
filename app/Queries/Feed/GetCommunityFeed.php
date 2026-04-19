<?php

namespace App\Queries\Feed;

use App\Models\Community;
use App\Models\Like;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class GetCommunityFeed
{
    public function paginated(Community $community, ?int $userId = null, int $perPage = 20): LengthAwarePaginator
    {
        $posts = $community->posts()
            ->with([
                'author:id,name,username,avatar',
                'comments' => fn ($q) => $q
                    ->whereNull('parent_id')
                    ->with([
                        'author:id,name,username,avatar',
                        'replies' => fn ($r) => $r->with(['author:id,name,username,avatar'])->take(3),
                    ])
                    ->latest()
                    ->take(3),
            ])
            ->withCount('likes', 'comments')
            ->orderByDesc('is_pinned')
            ->latest()
            ->paginate($perPage);

        $this->batchEnrich($posts->getCollection(), $userId);

        return $posts;
    }

    public function forShow(Community $community, ?int $userId = null, int $limit = 20): void
    {
        $community->load([
            'owner',
            'posts' => fn ($q) => $q
                ->with([
                    'author:id,name,avatar',
                    'comments' => fn ($cq) => $cq
                        ->whereNull('parent_id')
                        ->with([
                            'author:id,name,avatar',
                            'replies' => fn ($rq) => $rq->with(['author:id,name,avatar'])->take(3),
                        ])
                        ->latest()
                        ->take(3),
                ])
                ->withCount('likes', 'comments')
                ->orderByDesc('is_pinned')
                ->latest()
                ->take($limit),
        ]);
        $community->loadCount('members');

        $this->batchEnrich($community->posts, $userId);

        $community->posts->each(function ($post) {
            $commenters = $post->comments
                ->sortByDesc('created_at')
                ->unique('user_id')
                ->take(4)
                ->map(fn ($c) => ['name' => $c->author?->name, 'avatar' => $c->author?->avatar])
                ->values();

            $post->commenter_avatars = $commenters;
            $post->last_comment_at = $post->comments->max('created_at');
        });
    }

    /**
     * Enrich a single post with reaction data (delegates to batchEnrich).
     */
    public function enrichPost($post, ?int $userId): void
    {
        $this->batchEnrich(collect([$post]), $userId);
    }

    /**
     * Batch-load reaction counts and user reactions for posts + comments + replies
     * using 2 aggregate queries instead of N+1 per-item lookups.
     */
    private function batchEnrich(Collection $posts, ?int $userId): void
    {
        if ($posts->isEmpty()) {
            return;
        }

        // Collect all likeable IDs by type
        $postIds = $posts->pluck('id')->all();
        $commentIds = $posts->flatMap(fn ($p) => $p->comments->pluck('id'))->all();
        $replyIds = $posts->flatMap(fn ($p) => $p->comments->flatMap(fn ($c) => $c->replies->pluck('id')))->all();

        // Single query: reaction counts grouped by likeable
        $postReactions = $this->reactionCountsBatch('App\\Models\\Post', $postIds);
        $commentReactions = $this->reactionCountsBatch('App\\Models\\Comment', array_merge($commentIds, $replyIds));

        // Single query: current user's reactions
        $userPostLikes = collect();
        $userCommentLikes = collect();
        if ($userId) {
            $userPostLikes = Like::where('user_id', $userId)
                ->where('likeable_type', 'App\\Models\\Post')
                ->whereIn('likeable_id', $postIds)
                ->pluck('type', 'likeable_id');

            $userCommentLikes = Like::where('user_id', $userId)
                ->where('likeable_type', 'App\\Models\\Comment')
                ->whereIn('likeable_id', array_merge($commentIds, $replyIds))
                ->pluck('type', 'likeable_id');
        }

        foreach ($posts as $post) {
            $post->reactions = $postReactions->get($post->id, $this->emptyReactions());
            $post->user_reaction = $userPostLikes->get($post->id);
            $post->user_has_liked = (bool) $post->user_reaction;

            foreach ($post->comments as $comment) {
                $comment->reactions = $commentReactions->get($comment->id, $this->emptyReactions());
                $comment->user_reaction = $userCommentLikes->get($comment->id);
                $comment->user_has_liked = (bool) $comment->user_reaction;
                $comment->likes_count = collect($comment->reactions)->sum();

                foreach ($comment->replies as $reply) {
                    $reply->reactions = $commentReactions->get($reply->id, $this->emptyReactions());
                    $reply->user_reaction = $userCommentLikes->get($reply->id);
                    $reply->user_has_liked = (bool) $reply->user_reaction;
                    $reply->likes_count = collect($reply->reactions)->sum();
                }
            }
        }
    }

    /**
     * Batch query reaction counts: returns Collection keyed by likeable_id.
     */
    private function reactionCountsBatch(string $type, array $ids): Collection
    {
        if (empty($ids)) {
            return collect();
        }

        return Like::where('likeable_type', $type)
            ->whereIn('likeable_id', $ids)
            ->selectRaw('likeable_id, type, count(*) as cnt')
            ->groupBy('likeable_id', 'type')
            ->get()
            ->groupBy('likeable_id')
            ->map(function ($rows) {
                $counts = $this->emptyReactions();
                foreach ($rows as $row) {
                    if (isset($counts[$row->type])) {
                        $counts[$row->type] = $row->cnt;
                    }
                }

                return $counts;
            });
    }

    private function emptyReactions(): array
    {
        return ['like' => 0, 'handshake' => 0, 'trophy' => 0];
    }
}
