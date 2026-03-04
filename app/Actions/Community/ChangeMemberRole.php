<?php

namespace App\Actions\Community;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class ChangeMemberRole
{
    /** @throws AuthorizationException|\InvalidArgumentException */
    public function execute(User $actor, Community $community, User $target, string $newRole): CommunityMember
    {
        if (! in_array($newRole, CommunityMember::ROLES, true)) {
            throw new \InvalidArgumentException("Invalid role: {$newRole}");
        }

        if ($actor->id !== $community->owner_id) {
            throw new AuthorizationException('Only the community owner can change member roles.');
        }

        if ($target->id === $community->owner_id && $newRole !== CommunityMember::ROLE_ADMIN) {
            throw new AuthorizationException('Cannot demote the community owner.');
        }

        $member = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        $member->update(['role' => $newRole]);

        return $member->fresh();
    }
}
