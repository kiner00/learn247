<?php

namespace App\Actions\Account;

use App\Models\CommunityMember;
use App\Models\User;

class UpdateCommunityChat
{
    public function execute(User $user, int $communityId, bool $chatEnabled): void
    {
        $member = CommunityMember::where('user_id', $user->id)
            ->where('community_id', $communityId)
            ->firstOrFail();

        $member->update(['chat_enabled' => $chatEnabled]);
    }
}
