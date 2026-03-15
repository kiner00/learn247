<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\CommunityLevelPerk;
use App\Models\CommunityMember;
use App\Queries\Community\GetLeaderboard;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    public function show(Community $community, GetLeaderboard $query): Response
    {
        $userId = auth()->id();

        $data = $query->execute($community, $userId);

        $myMembership = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $userId)
            ->with('user:id,name,username,avatar')
            ->first();

        $totalMembers = $community->members()->count();
        $thresholds   = CommunityMember::LEVEL_THRESHOLDS;
        $levelCounts  = array_fill(1, count($thresholds), 0);

        CommunityMember::where('community_id', $community->id)
            ->pluck('points')
            ->each(function ($pts) use (&$levelCounts) {
                $level = CommunityMember::computeLevel($pts);
                $levelCounts[$level] = ($levelCounts[$level] ?? 0) + 1;
            });

        $perks = CommunityLevelPerk::where('community_id', $community->id)->pluck('description', 'level');

        $levelDistribution = collect(range(1, count($thresholds)))->map(fn ($l) => [
            'level'     => $l,
            'count'     => $levelCounts[$l] ?? 0,
            'percent'   => $totalMembers > 0 ? round(($levelCounts[$l] ?? 0) / $totalMembers * 100) : 0,
            'perk'      => $perks[$l] ?? null,
            'threshold' => $thresholds[$l - 1],
        ])->all();

        return Inertia::render('Communities/Leaderboard', [
            'community'         => $community,
            'myName'            => $myMembership?->user?->name,
            'myAvatar'          => $myMembership?->user?->avatar,
            'myPoints'          => $data['my_points'],
            'myLevel'           => $data['my_level'],
            'pointsToNextLevel' => $data['points_to_next'],
            'levelDistribution' => $levelDistribution,
            'leaderboard'       => $data['leaderboard'],
            'leaderboard30'     => $data['leaderboard_30_days'],
            'leaderboard7'      => $data['leaderboard_7_days'],
            'updatedAt'         => now()->format('M j, Y g:ia'),
        ]);
    }
}
