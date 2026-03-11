<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FeedController extends Controller
{
    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        $this->requireMembership($request, $community);

        $userId = $request->user()->id;

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
            ->paginate(20);

        $posts->each(function ($post) use ($userId) {
            $post->reactions     = $this->reactionCounts($post->likes);
            $post->user_reaction = $post->likes->firstWhere('user_id', $userId)?->type;
            $post->comments->each(function ($comment) use ($userId) {
                $comment->reactions     = $this->reactionCounts($comment->likes);
                $comment->user_reaction = $comment->likes->firstWhere('user_id', $userId)?->type;
                $comment->likes_count   = $comment->likes->count();
                $comment->replies->each(function ($reply) use ($userId) {
                    $reply->reactions     = $this->reactionCounts($reply->likes);
                    $reply->user_reaction = $reply->likes->firstWhere('user_id', $userId)?->type;
                    $reply->likes_count   = $reply->likes->count();
                });
            });
        });

        return PostResource::collection($posts);
    }

    public function show(Request $request, Post $post): PostResource
    {
        $this->requireMembership($request, $post->community);

        $userId = $request->user()->id;

        $post->load([
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
        ])->loadCount('likes', 'comments');

        $post->reactions     = $this->reactionCounts($post->likes);
        $post->user_reaction = $post->likes->firstWhere('user_id', $userId)?->type;

        return new PostResource($post);
    }

    private function requireMembership(Request $request, Community $community): void
    {
        $user = $request->user();

        if ($community->owner_id === $user->id) {
            return;
        }

        if ($community->isFree()) {
            abort_unless(
                CommunityMember::where('community_id', $community->id)->where('user_id', $user->id)->exists(),
                403,
                'You must be a member of this community.'
            );
            return;
        }

        abort_unless(
            Subscription::where('community_id', $community->id)
                ->where('user_id', $user->id)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists(),
            403,
            'An active membership is required.'
        );
    }

    private function reactionCounts(\Illuminate\Support\Collection $likes): array
    {
        $grouped = $likes->groupBy('type');
        return [
            'like'      => $grouped->get('like',      collect())->count(),
            'handshake' => $grouped->get('handshake', collect())->count(),
            'trophy'    => $grouped->get('trophy',    collect())->count(),
        ];
    }
}
