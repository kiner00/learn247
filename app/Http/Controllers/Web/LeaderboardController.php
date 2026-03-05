<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\CommunityMember;
use App\Models\Community;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    public function show(Community $community): Response
    {
        $userId = auth()->id();

        // ── Current user's stats ─────────────────────────────────────────────
        $myMembership = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $userId)
            ->with('user:id,name,username')
            ->first();

        $myPoints    = $myMembership?->points ?? 0;
        $myLevel     = CommunityMember::computeLevel($myPoints);
        $thresholds  = CommunityMember::LEVEL_THRESHOLDS;
        $nextThresh  = $thresholds[$myLevel] ?? null; // null = max level
        $ptsToNext   = $nextThresh !== null ? $nextThresh - $myPoints : null;

        // ── Level distribution ───────────────────────────────────────────────
        $totalMembers = $community->members()->count();
        $levelCounts  = array_fill(1, count($thresholds), 0);

        CommunityMember::where('community_id', $community->id)
            ->pluck('points')
            ->each(function ($pts) use (&$levelCounts, $thresholds) {
                $level = CommunityMember::computeLevel($pts);
                $levelCounts[$level] = ($levelCounts[$level] ?? 0) + 1;
            });

        $levelDistribution = collect(range(1, count($thresholds)))->map(fn ($l) => [
            'level'   => $l,
            'count'   => $levelCounts[$l] ?? 0,
            'percent' => $totalMembers > 0 ? round(($levelCounts[$l] ?? 0) / $totalMembers * 100) : 0,
        ])->all();

        // ── All-time leaderboard (stored points) ─────────────────────────────
        $allTime = CommunityMember::where('community_id', $community->id)
            ->with('user:id,name,username')
            ->orderByDesc('points')
            ->take(10)
            ->get()
            ->map(fn ($m) => [
                'user_id'  => $m->user_id,
                'name'     => $m->user?->name ?? 'Unknown',
                'username' => $m->user?->username,
                'points'   => $m->points,
                'level'    => CommunityMember::computeLevel($m->points),
            ])->values()->all();

        // ── Period leaderboards ───────────────────────────────────────────────
        $leaderboard30 = $this->periodLeaderboard($community, 30);
        $leaderboard7  = $this->periodLeaderboard($community, 7);

        return Inertia::render('Communities/Leaderboard', [
            'community'         => $community,
            'myName'            => $myMembership?->user?->name,
            'myPoints'          => $myPoints,
            'myLevel'           => $myLevel,
            'pointsToNextLevel' => $ptsToNext,
            'levelDistribution' => $levelDistribution,
            'leaderboard'       => $allTime,
            'leaderboard30'     => $leaderboard30,
            'leaderboard7'      => $leaderboard7,
            'updatedAt'         => now()->format('M j, Y g:ia'),
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

        $users = User::whereIn('id', $userIds)->select('id', 'name', 'username')->get()->keyBy('id');

        return $userIds->map(function ($uid) use ($postPts, $commentPts, $users) {
            return [
                'user_id'  => $uid,
                'name'     => $users[$uid]?->name ?? 'Unknown',
                'username' => $users[$uid]?->username,
                'points'   => (int) ($postPts[$uid] ?? 0) + (int) ($commentPts[$uid] ?? 0),
            ];
        })->sortByDesc('points')->values()->take(10)->all();
    }
}
