<?php

namespace App\Http\Controllers\Api;

use App\Actions\Community\JoinCommunity;
use App\Http\Controllers\Controller;
use App\Http\Resources\CommunityResource;
use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityLevelPerk;
use App\Models\CommunityMember;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class CommunityController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $search   = $request->string('search')->trim()->toString();
        $category = $request->string('category')->trim()->toString();
        $sort     = in_array($request->input('sort'), ['popular', 'latest']) ? $request->input('sort') : 'latest';

        $communities = Community::with('owner')
            ->withCount('members')
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when($category && $category !== 'All', fn ($q) => $q->where('category', $category))
            ->when($sort === 'popular', fn ($q) => $q->orderByDesc('members_count'))
            ->when($sort === 'latest',  fn ($q) => $q->latest())
            ->paginate(15)
            ->withQueryString();

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
            'community'   => new CommunityResource($community),
            'membership'  => $membership ? [
                'role'      => $membership->role,
                'points'    => $membership->points,
                'level'     => CommunityMember::computeLevel($membership->points),
                'joined_at' => $membership->joined_at,
            ] : null,
            'has_access'  => $hasAccess,
        ]);
    }

    public function join(Request $request, Community $community, JoinCommunity $action): JsonResponse
    {
        $user        = $request->user();
        $beforeCount = $community->members()->count();

        $action->execute($user, $community);

        $afterCount = $beforeCount + 1;

        if ($community->owner_id !== $user->id) {
            Notification::create([
                'user_id'      => $community->owner_id,
                'actor_id'     => $user->id,
                'community_id' => $community->id,
                'type'         => 'new_member',
                'data'         => ['message' => "{$user->name} joined {$community->name}"],
            ]);
        }

        $milestones = [100, 500, 1_000, 10_000, 100_000, 1_000_000];
        $labels     = [100 => '100 🥉', 500 => '500 🥈', 1_000 => '1k 🥇', 10_000 => '10k 💎', 100_000 => '100k 🏆', 1_000_000 => '1M 🌟'];
        foreach ($milestones as $milestone) {
            if ($beforeCount < $milestone && $afterCount >= $milestone) {
                Notification::create([
                    'user_id'      => $community->owner_id,
                    'actor_id'     => null,
                    'community_id' => $community->id,
                    'type'         => 'milestone',
                    'data'         => ['milestone' => $milestone, 'message' => "🎉 {$community->name} just hit {$labels[$milestone]} members!"],
                ]);
                break;
            }
        }

        return response()->json(['message' => 'You have joined the community!'], 201);
    }

    public function leaderboard(Request $request, Community $community): JsonResponse
    {
        $userId = $request->user()->id;

        $myMembership = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $userId)
            ->with('user:id,name,username')
            ->first();

        $myPoints   = $myMembership?->points ?? 0;
        $myLevel    = CommunityMember::computeLevel($myPoints);
        $thresholds = CommunityMember::LEVEL_THRESHOLDS;
        $nextThresh = $thresholds[$myLevel] ?? null;
        $ptsToNext  = $nextThresh !== null ? $nextThresh - $myPoints : null;

        $allTime = CommunityMember::where('community_id', $community->id)
            ->with('user:id,name,username,avatar')
            ->orderByDesc('points')
            ->take(10)
            ->get()
            ->map(fn ($m) => [
                'user_id'  => $m->user_id,
                'name'     => $m->user?->name ?? 'Unknown',
                'username' => $m->user?->username,
                'avatar'   => $m->user?->avatar,
                'points'   => $m->points,
                'level'    => CommunityMember::computeLevel($m->points),
            ])->values();

        $leaderboard30 = $this->periodLeaderboard($community, 30);
        $leaderboard7  = $this->periodLeaderboard($community, 7);

        $perks = CommunityLevelPerk::where('community_id', $community->id)
            ->pluck('description', 'level');

        return response()->json([
            'my_points'          => $myPoints,
            'my_level'           => $myLevel,
            'points_to_next'     => $ptsToNext,
            'leaderboard'        => $allTime,
            'leaderboard_30_days' => $leaderboard30,
            'leaderboard_7_days'  => $leaderboard7,
            'level_perks'        => $perks,
        ]);
    }

    private function periodLeaderboard(Community $community, int $days): array
    {
        $since = Carbon::now()->subDays($days);

        $postPts = Post::where('community_id', $community->id)
            ->where('created_at', '>=', $since)
            ->whereNull('deleted_at')
            ->selectRaw('user_id, COUNT(*) * ' . CommunityMember::POINTS_POST . ' as pts')
            ->groupBy('user_id')
            ->pluck('pts', 'user_id');

        $commentPts = Comment::where('community_id', $community->id)
            ->where('created_at', '>=', $since)
            ->whereNull('deleted_at')
            ->selectRaw('user_id, COUNT(*) * ' . CommunityMember::POINTS_COMMENT . ' as pts')
            ->groupBy('user_id')
            ->pluck('pts', 'user_id');

        $userIds = $postPts->keys()->merge($commentPts->keys())->unique();

        if ($userIds->isEmpty()) return [];

        $users = User::whereIn('id', $userIds)->select('id', 'name', 'username', 'avatar')->get()->keyBy('id');

        return $userIds->map(fn ($uid) => [
            'user_id'  => $uid,
            'name'     => $users[$uid]?->name ?? 'Unknown',
            'username' => $users[$uid]?->username,
            'avatar'   => $users[$uid]?->avatar,
            'points'   => (int) ($postPts[$uid] ?? 0) + (int) ($commentPts[$uid] ?? 0),
        ])->sortByDesc('points')->values()->take(10)->all();
    }
}
