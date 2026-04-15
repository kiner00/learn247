<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\Subscription;
use App\Queries\Feed\GetCommunityFeed;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FeedController extends Controller
{
    public function index(Request $request, Community $community, GetCommunityFeed $feed): AnonymousResourceCollection
    {
        $this->requireMembership($request, $community);

        $posts = $feed->paginated($community, $request->user()->id);

        return PostResource::collection($posts);
    }

    public function show(Request $request, Post $post, GetCommunityFeed $feed): PostResource
    {
        $this->requireMembership($request, $post->community);

        $post->load([
            'author:id,name,username,avatar',
            'comments' => fn ($q) => $q
                ->whereNull('parent_id')
                ->with([
                    'author:id,name,username,avatar',
                    'replies' => fn ($r) => $r->with(['author:id,name,username,avatar'])->take(3),
                ])
                ->latest(),
        ])->loadCount('likes', 'comments');

        $feed->enrichPost($post, $request->user()->id);

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
                403, 'You must be a member of this community.'
            );
            return;
        }

        abort_unless(
            Subscription::where('community_id', $community->id)
                ->where('user_id', $user->id)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists(),
            403, 'An active membership is required.'
        );
    }
}
