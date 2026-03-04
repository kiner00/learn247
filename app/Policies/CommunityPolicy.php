<?php

namespace App\Policies;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;

class CommunityPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Community $community): bool
    {
        if (! $community->is_private) {
            return true;
        }

        if (! $user) {
            return false;
        }

        return CommunityMember::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Community $community): bool
    {
        return $user->id === $community->owner_id;
    }

    public function delete(User $user, Community $community): bool
    {
        return $user->id === $community->owner_id;
    }

    public function manageMember(User $user, Community $community): bool
    {
        $member = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->first();

        return $member && $member->canModerate();
    }
}
