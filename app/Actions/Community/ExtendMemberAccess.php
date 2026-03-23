<?php

namespace App\Actions\Community;

use App\Models\Community;
use App\Models\CommunityMember;

class ExtendMemberAccess
{
    public function execute(Community $community, array $userIds, int $months): int
    {
        $members = CommunityMember::where('community_id', $community->id)
            ->whereIn('user_id', $userIds)
            ->where('membership_type', CommunityMember::MEMBERSHIP_FREE)
            ->get();

        foreach ($members as $member) {
            $base = ($member->expires_at && $member->expires_at->isFuture())
                ? $member->expires_at
                : now();

            $member->update(['expires_at' => $base->addMonths($months)]);
        }

        return $members->count();
    }
}
