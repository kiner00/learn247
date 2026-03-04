<?php

namespace App\Actions\Community;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class JoinCommunity
{
    public function execute(User $user, Community $community): CommunityMember
    {
        if (CommunityMember::where('community_id', $community->id)->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'community' => 'You are already a member of this community.',
            ]);
        }

        if (! $community->isFree()) {
            throw ValidationException::withMessages([
                'community' => 'This is a paid community. Please subscribe to join.',
            ]);
        }

        return CommunityMember::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'role'         => CommunityMember::ROLE_MEMBER,
            'joined_at'    => now(),
        ]);
    }
}
