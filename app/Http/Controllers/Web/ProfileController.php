<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CommunityMember;
use App\Models\User;
use App\Queries\Profile\GetProfileData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function me(Request $request, GetProfileData $query): Response
    {
        return $this->renderProfile($request->user(), $request, isOwn: true, query: $query);
    }

    public function show(Request $request, string $username, GetProfileData $query): Response
    {
        $user  = User::where('username', $username)->firstOrFail();
        $isOwn = $request->user()?->id === $user->id;

        return $this->renderProfile($user, $request, isOwn: $isOwn, query: $query);
    }

    private function renderProfile(User $user, Request $request, bool $isOwn, GetProfileData $query): Response
    {
        $data        = $query->execute($user, $isOwn);
        $memberships = $data['memberships'];

        $membershipsMapped = $memberships->map(fn ($m) => [
            'community_id'  => $m->community_id,
            'name'          => $m->community?->name,
            'slug'          => $m->community?->slug,
            'avatar'        => $m->community?->avatar,
            'price'         => $m->community?->price,
            'members_count' => CommunityMember::where('community_id', $m->community_id)->count(),
            'joined_at'     => $m->joined_at,
        ]);

        $communitySlug      = $request->get('community');
        $selectedMembership = $membershipsMapped->firstWhere('slug', $communitySlug) ?? $membershipsMapped->first();
        $selectedCommunityId = $selectedMembership['community_id'] ?? null;
        $contributionsCount  = $query->getContributionsCount($user, $selectedCommunityId);

        return Inertia::render('Profile/Show', [
            'profileUser'        => [
                'id'                => $user->id,
                'name'              => $user->name,
                'username'          => $user->username,
                'bio'               => $user->bio,
                'avatar'            => $user->avatar,
                'location'          => $user->location,
                'created_at'        => $user->created_at,
                'crz_token_balance' => $isOwn ? (float) $user->crz_token_balance : null,
            ],
            'isOwn'              => $isOwn,
            'totalPoints'        => $data['total_points'],
            'myLevel'            => $data['level'],
            'pointsToNextLevel'  => $data['points_to_next'],
            'memberships'        => $membershipsMapped->values(),
            'activityMap'        => $data['activity_map'],
            'contributionsCount' => $contributionsCount,
            'selectedCommunity'  => $selectedMembership ? $selectedMembership['name'] : null,
            'badges'             => $data['badges'],
        ]);
    }
}
