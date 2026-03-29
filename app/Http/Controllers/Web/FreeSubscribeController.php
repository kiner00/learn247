<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Support\CacheKeys;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FreeSubscribeController extends Controller
{
    public function store(Request $request, Community $community): RedirectResponse
    {
        $user = $request->user();

        // Already a member (free or paid) — nothing to do
        $existing = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return redirect()->route('communities.classroom', $community);
        }

        CommunityMember::create([
            'community_id'    => $community->id,
            'user_id'         => $user->id,
            'role'            => CommunityMember::ROLE_MEMBER,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
            'joined_at'       => now(),
        ]);

        CacheKeys::flushUserMembership($user->id);

        return redirect()->route('communities.classroom', $community)
            ->with('success', 'You\'ve subscribed for free! Enjoy the courses.');
    }
}
