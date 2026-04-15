<?php

namespace App\Actions\Community;

use App\Models\Community;
use App\Models\CommunityMember;

class SetMemberExpiry
{
    public function execute(Community $community, array $userIds, ?int $months): int
    {
        $expiry = $months === null ? null : now()->addMonths($months);

        return CommunityMember::where('community_id', $community->id)
            ->whereIn('user_id', $userIds)
            ->where('membership_type', CommunityMember::MEMBERSHIP_FREE)
            ->update(['expires_at' => $expiry]);
    }
}
