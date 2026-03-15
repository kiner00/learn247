<?php

namespace App\Actions\Account;

use App\Models\CommunityMember;
use App\Models\User;

class UpdateCommunityNotificationPrefs
{
    public function execute(User $user, int $communityId, array $prefs): void
    {
        $member = CommunityMember::where('user_id', $user->id)
            ->where('community_id', $communityId)
            ->firstOrFail();

        $member->update(['notif_prefs' => $prefs]);
    }
}
