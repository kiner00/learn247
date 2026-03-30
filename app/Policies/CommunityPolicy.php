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
        return true;
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
        return $user->id === $community->owner_id || $user->isSuperAdmin();
    }

    /**
     * Owner OR admin member OR super-admin — replaces canManage() in controllers.
     */
    public function manage(User $user, Community $community): bool
    {
        if ($user->id === $community->owner_id || $user->isSuperAdmin()) {
            return true;
        }

        return CommunityMember::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->where('role', CommunityMember::ROLE_ADMIN)
            ->exists();
    }

    /**
     * Owner OR admin OR moderator — for content moderation.
     */
    public function moderate(User $user, Community $community): bool
    {
        if ($user->id === $community->owner_id || $user->isSuperAdmin()) {
            return true;
        }

        $member = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->first();

        return $member && $member->canModerate();
    }

    public function viewAnalytics(User $user, Community $community): bool
    {
        if ($user->id === $community->owner_id) {
            return true;
        }

        $member = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->first();

        return $member && $member->isAdmin();
    }

    public function manageMember(User $user, Community $community): bool
    {
        $member = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->first();

        return $member && $member->canModerate();
    }
}
