<?php

namespace App\Http\Controllers\Api;

use App\Actions\Community\JoinCommunity;
use App\Http\Controllers\Controller;
use App\Http\Resources\CommunityResource;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Subscription;
use App\Queries\Community\GetLeaderboard;
use App\Queries\Community\ListCommunities;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CommunityController extends Controller
{
    public function index(Request $request, ListCommunities $query): AnonymousResourceCollection
    {
        $communities = $query->execute(
            $request->string('search')->trim()->toString(),
            $request->string('category')->trim()->toString(),
            $request->input('sort', 'latest'),
        );

        if ($userId = $request->user()?->id) {
            $memberIds = CommunityMember::where('user_id', $userId)
                ->pluck('community_id')
                ->flip();

            $communities->each(function (Community $c) use ($memberIds) {
                $c->is_member = $memberIds->has($c->id);
                $c->is_admin  = false;
            });
        }

        return CommunityResource::collection($communities);
    }

    public function show(Request $request, Community $community): JsonResponse
    {
        $community->load('owner')->loadCount('members');

        $userId     = $request->user()?->id;
        $membership = $userId ? $community->members()->where('user_id', $userId)->first() : null;
        $isOwner    = $userId && $community->owner_id === $userId;

        $hasAccess = $isOwner
            || ($community->isFree() && $membership)
            || (! $community->isFree() && Subscription::where('community_id', $community->id)
                ->where('user_id', $userId)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists());

        return response()->json([
            'community'  => new CommunityResource($community),
            'membership' => $membership ? [
                'role'      => $membership->role,
                'points'    => $membership->points,
                'level'     => CommunityMember::computeLevel($membership->points),
                'joined_at' => $membership->joined_at,
            ] : null,
            'has_access' => $hasAccess,
        ]);
    }

    public function join(Request $request, Community $community, JoinCommunity $action): JsonResponse
    {
        $action->execute($request->user(), $community);

        return response()->json(['message' => 'You have joined the community!'], 201);
    }

    public function leaderboard(Request $request, Community $community, GetLeaderboard $query): JsonResponse
    {
        return response()->json($query->execute($community, $request->user()->id));
    }
}
