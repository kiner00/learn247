<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\EmailUnsubscribe;
use Illuminate\Http\Request;

class EmailUnsubscribeController extends Controller
{
    public function unsubscribe(Request $request, Community $community, CommunityMember $member)
    {
        // Verify the member belongs to this community
        abort_unless($member->community_id === $community->id, 404);

        $user = $member->user;
        abort_unless($user, 404);

        EmailUnsubscribe::firstOrCreate([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ], [
            'reason' => 'manual',
            'unsubscribed_at' => now(),
        ]);

        return inertia('Email/Unsubscribed', [
            'communityName' => $community->name,
        ]);
    }
}
