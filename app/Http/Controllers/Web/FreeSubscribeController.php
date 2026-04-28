<?php

namespace App\Http\Controllers\Web;

use App\Actions\Auth\GuestCheckout;
use App\Actions\Auth\IssueGuestPassword;
use App\Events\MemberJoined;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Support\CacheKeys;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        $member = CommunityMember::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'role' => CommunityMember::ROLE_MEMBER,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
            'joined_at' => now(),
        ]);

        CacheKeys::flushUserMembership($user->id);

        MemberJoined::dispatch($member);

        return redirect()->route('communities.classroom', $community)
            ->with('success', 'You\'ve subscribed for free! Enjoy the courses.');
    }

    public function guestStore(
        Request $request,
        Community $community,
        GuestCheckout $checkout,
        IssueGuestPassword $issuePassword,
    ): RedirectResponse {
        if ($community->isPendingDeletion()) {
            return back()->withErrors(['email' => 'This community is no longer accepting new members.']);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email'],
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $user = $checkout->findOrCreateUser($data);

        $existing = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            Auth::login($user, true);

            return redirect()->route('communities.classroom', $community);
        }

        $member = CommunityMember::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'role' => CommunityMember::ROLE_MEMBER,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
            'joined_at' => now(),
        ]);

        CacheKeys::flushUserMembership($user->id);

        MemberJoined::dispatch($member);

        $issuePassword->execute($user, $community);

        Auth::login($user, true);

        return redirect()->route('communities.classroom', $community)
            ->with('success', 'You\'re subscribed! Check your email for login details.');
    }
}
