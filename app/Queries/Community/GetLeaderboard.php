<?php

namespace App\Queries\Community;

use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityLevelPerk;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Carbon;

class GetLeaderboard
{
    public function execute(Community $community, int $userId): array
    {
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

        $perks = CommunityLevelPerk::where('community_id', $community->id)
            ->pluck('description', 'level');

        return [
            'my_points'           => $myPoints,
            'my_level'            => $myLevel,
            'points_to_next'      => $ptsToNext,
            'leaderboard'         => $allTime,
            'leaderboard_30_days' => $this->periodLeaderboard($community, 30),
            'leaderboard_7_days'  => $this->periodLeaderboard($community, 7),
            'level_perks'         => $perks,
        ];
    }

    public function topMembers(Community $community, int $limit = 5): array
    {
        return CommunityMember::where('community_id', $community->id)
            ->with('user:id,name,username,avatar')
            ->orderByDesc('points')
            ->take($limit)
            ->get()
            ->map(fn ($m) => [
                'user_id'  => $m->user_id,
                'name'     => $m->user?->name,
                'username' => $m->user?->username,
                'avatar'   => $m->user?->avatar,
                'points'   => $m->points,
                'level'    => CommunityMember::computeLevel($m->points),
            ])->all();
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

        if ($userIds->isEmpty()) {
            return [];
        }

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
