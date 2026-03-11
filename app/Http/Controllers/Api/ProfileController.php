<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\Comment;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ProfileController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        return $this->profileResponse($request->user(), $request, isOwn: true);
    }

    public function show(Request $request, string $username): JsonResponse
    {
        $user  = User::where('username', $username)->firstOrFail();
        $isOwn = $request->user()->id === $user->id;

        return $this->profileResponse($user, $request, isOwn: $isOwn);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['sometimes', 'string', 'max:255'],
            'bio'      => ['sometimes', 'nullable', 'string', 'max:500'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'avatar'   => ['sometimes', 'nullable', 'url', 'max:500'],
        ]);

        $request->user()->update($data);

        return response()->json([
            'message' => 'Profile updated.',
            'user'    => [
                'id'       => $request->user()->id,
                'name'     => $request->user()->name,
                'username' => $request->user()->username,
                'bio'      => $request->user()->bio,
                'location' => $request->user()->location,
                'avatar'   => $request->user()->avatar,
            ],
        ]);
    }

    private function profileResponse(User $user, Request $request, bool $isOwn): JsonResponse
    {
        $memberships = CommunityMember::where('user_id', $user->id)
            ->with('community:id,name,slug,avatar,price')
            ->get()
            ->map(fn ($m) => [
                'community_id'  => $m->community_id,
                'name'          => $m->community?->name,
                'slug'          => $m->community?->slug,
                'avatar'        => $m->community?->avatar,
                'role'          => $m->role,
                'points'        => $m->points,
                'level'         => CommunityMember::computeLevel($m->points),
                'joined_at'     => $m->joined_at,
            ]);

        $totalPoints = CommunityMember::where('user_id', $user->id)->sum('points');
        $myLevel     = CommunityMember::computeLevel((int) $totalPoints);
        $thresholds  = CommunityMember::LEVEL_THRESHOLDS;
        $nextThresh  = $thresholds[$myLevel] ?? null;
        $ptsToNext   = $nextThresh !== null ? $nextThresh - $totalPoints : null;

        $since = Carbon::now()->subWeeks(52)->startOfDay();

        $postDates = Post::where('user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->whereNull('deleted_at')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as cnt')
            ->groupBy('date')
            ->pluck('cnt', 'date');

        $commentDates = Comment::where('user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->whereNull('deleted_at')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as cnt')
            ->groupBy('date')
            ->pluck('cnt', 'date');

        $activityMap = [];
        $cursor = $since->copy();
        while ($cursor <= now()) {
            $d     = $cursor->toDateString();
            $count = (int) ($postDates[$d] ?? 0) + (int) ($commentDates[$d] ?? 0);
            if ($count > 0) $activityMap[$d] = $count;
            $cursor->addDay();
        }

        $earnedBadgeIds = UserBadge::where('user_id', $user->id)->pluck('earned_at', 'badge_id');

        if ($isOwn) {
            $allBadges = Badge::whereNull('community_id')->whereNotNull('key')->orderBy('sort_order')->get();
            $badges    = $allBadges->map(fn ($b) => [
                'key'         => $b->key,
                'name'        => $b->name,
                'icon'        => $b->icon,
                'description' => $b->description,
                'earned'      => $earnedBadgeIds->has($b->id),
                'earned_at'   => $earnedBadgeIds->get($b->id)?->toDateString(),
            ])->values();
        } else {
            $badges = Badge::whereNull('community_id')
                ->whereNotNull('key')
                ->whereIn('id', $earnedBadgeIds->keys())
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($b) => [
                    'key'         => $b->key,
                    'name'        => $b->name,
                    'icon'        => $b->icon,
                    'description' => $b->description,
                    'earned'      => true,
                    'earned_at'   => $earnedBadgeIds->get($b->id)?->toDateString(),
                ])->values();
        }

        return response()->json([
            'user'               => [
                'id'         => $user->id,
                'name'       => $user->name,
                'username'   => $user->username,
                'bio'        => $user->bio,
                'avatar'     => $user->avatar,
                'location'   => $user->location,
                'created_at' => $user->created_at,
            ],
            'is_own'             => $isOwn,
            'total_points'       => (int) $totalPoints,
            'level'              => $myLevel,
            'points_to_next'     => $ptsToNext,
            'memberships'        => $memberships->values(),
            'activity_map'       => $activityMap,
            'badges'             => $badges,
        ]);
    }
}
