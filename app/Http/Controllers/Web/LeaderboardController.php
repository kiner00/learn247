<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Queries\Community\GetLeaderboard;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    public function show(Community $community, GetLeaderboard $query): Response
    {
        $userId = auth()->id();

        $data              = $query->execute($community, $userId);
        $levelDistribution = $query->levelDistribution($community);
        $myMembership      = $community->members()
            ->where('user_id', $userId)
            ->with('user:id,name,username,avatar')
            ->first();

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
