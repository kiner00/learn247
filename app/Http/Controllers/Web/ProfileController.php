<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CommunityMember;
use App\Models\Comment;
use App\Models\Post;
use App\Models\LessonCompletion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function me(Request $request): Response
    {
        return $this->renderProfile($request->user(), $request, isOwn: true);
    }

    public function show(Request $request, string $username): Response
    {
        $user = User::where('username', $username)->firstOrFail();
        $isOwn = $request->user()?->id === $user->id;

        return $this->renderProfile($user, $request, isOwn: $isOwn);
    }

    private function renderProfile(User $user, Request $request, bool $isOwn): Response
    {
        // ── Memberships ──────────────────────────────────────────────────────
        $memberships = CommunityMember::where('user_id', $user->id)
            ->with('community:id,name,slug,avatar,price')
            ->withCount(['community as member_count' => fn ($q) => $q
                ->join('community_members as cm2', 'communities.id', '=', 'cm2.community_id')
            ])
            ->get()
            ->map(fn ($m) => [
                'community_id'   => $m->community_id,
                'name'           => $m->community?->name,
                'slug'           => $m->community?->slug,
                'avatar'         => $m->community?->avatar,
                'price'          => $m->community?->price,
                'members_count'  => CommunityMember::where('community_id', $m->community_id)->count(),
                'joined_at'      => $m->joined_at,
            ]);

        // ── Level (aggregate across all communities) ─────────────────────────
        $totalPoints = CommunityMember::where('user_id', $user->id)->sum('points');
        $myLevel     = CommunityMember::computeLevel((int) $totalPoints);
        $thresholds  = CommunityMember::LEVEL_THRESHOLDS;
        $nextThresh  = $thresholds[$myLevel] ?? null;
        $ptsToNext   = $nextThresh !== null ? $nextThresh - $totalPoints : null;

        // ── Activity heatmap (past 52 weeks) ─────────────────────────────────
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
            $d = $cursor->toDateString();
            $count = (int) ($postDates[$d] ?? 0) + (int) ($commentDates[$d] ?? 0);
            if ($count > 0) $activityMap[$d] = $count;
            $cursor->addDay();
        }

        // ── Contributions (posts + comments in selected community) ────────────
        $communitySlug       = $request->get('community');
        $selectedMembership  = $memberships->firstWhere('slug', $communitySlug) ?? $memberships->first();
        $contributionsCount  = 0;
        $selectedCommunityId = $selectedMembership['community_id'] ?? null;

        if ($selectedCommunityId) {
            $contributionsCount =
                Post::where('user_id', $user->id)->where('community_id', $selectedCommunityId)->whereNull('deleted_at')->count()
                + Comment::where('user_id', $user->id)->where('community_id', $selectedCommunityId)->whereNull('deleted_at')->count();
        }

        return Inertia::render('Profile/Show', [
            'profileUser'         => [
                'id'         => $user->id,
                'name'       => $user->name,
                'username'   => $user->username,
                'bio'        => $user->bio,
                'created_at' => $user->created_at,
            ],
            'isOwn'               => $isOwn,
            'totalPoints'         => (int) $totalPoints,
            'myLevel'             => $myLevel,
            'pointsToNextLevel'   => $ptsToNext,
            'memberships'         => $memberships->values(),
            'activityMap'         => $activityMap,
            'contributionsCount'  => $contributionsCount,
            'selectedCommunity'   => $selectedMembership ? $selectedMembership['name'] : null,
        ]);
    }
}
