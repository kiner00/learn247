<?php

namespace App\Actions\Community;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;

class ToggleMemberBlock
{
    /**
     * Toggle the blocked status of a community member.
     * Returns the new status string ('blocked' or 'unblocked').
     */
    public function execute(User $actor, Community $community, User $target): string
    {
        $actorMember = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $actor->id)
            ->first();

        abort_unless($actor->id === $community->owner_id || $actorMember?->canModerate(), 403);
        abort_if($target->id === $community->owner_id, 403);

        $member = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        $member->update(['is_blocked' => ! $member->is_blocked]);

        return $member->is_blocked ? 'blocked' : 'unblocked';
    }
}
