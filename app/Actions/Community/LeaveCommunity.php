<?php

namespace App\Actions\Community;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use App\Support\CacheKeys;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class LeaveCommunity
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $user, Community $community): void
    {
        if ($user->id === $community->owner_id) {
            throw new AuthorizationException('The community owner cannot leave. Transfer ownership or delete the community instead.');
        }

        $member = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $member) {
            throw ValidationException::withMessages([
                'community' => 'You are not a member of this community.',
            ]);
        }

        $member->delete();

        CacheKeys::flushUserMembership($user->id);
    }
}
