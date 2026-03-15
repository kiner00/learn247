<?php

namespace App\Actions\Account;

use App\Models\CommunityMember;
use App\Models\User;

class UpdateMembershipVisibility
{
    public function execute(User $user, int $communityId, bool $showOnProfile): void
    {
        $member = CommunityMember::where('user_id', $user->id)
            ->where('community_id', $communityId)
            ->firstOrFail();

        $member->update(['show_on_profile' => $showOnProfile]);
    }
}
