<?php

namespace App\Actions\Community;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class RemoveMember
{
    /** @throws AuthorizationException */
    public function execute(User $actor, Community $community, User $target): void
    {
        if ($target->id === $community->owner_id) {
            throw new AuthorizationException('Cannot remove the community owner.');
        }

        $actorMember = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $actor->id)
            ->firstOrFail();

        if (! $actorMember->canModerate()) {
            throw new AuthorizationException('You do not have permission to remove members.');
        }

        CommunityMember::where('community_id', $community->id)
            ->where('user_id', $target->id)
            ->delete();
    }
}
