<?php

namespace App\Services\Community;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Subscription;
use App\Models\User;

/**
 * Single source of truth for community membership / access checks.
 * Used by both Web and API controllers so the logic never diverges.
 */
class MembershipAccessService
{
    public function hasActiveMembership(User $user, Community $community): bool
    {
        if ($community->owner_id === $user->id) {
            return true;
        }

        $member = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->first();

        if ($community->isFree()) {
            return $member !== null && ! $this->isMemberExpired($member);
        }

        // Paid community: either a trial/invite-grant row with a FUTURE expires_at,
        // OR an active, un-expired subscription. A free-type row with no expires_at
        // on a paid community does NOT grant access (subscription is required).
        if ($member
            && $member->membership_type === CommunityMember::MEMBERSHIP_FREE
            && $member->expires_at !== null
            && $member->expires_at->isFuture()
        ) {
            return true;
        }

        return Subscription::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }

    public function assertMembership(User $user, Community $community): void
    {
        abort_unless(
            $this->hasActiveMembership($user, $community),
            403,
            $community->isFree()
                ? 'You must be a member of this community.'
                : 'An active membership is required.'
        );
    }

    /** True when the user previously had a free trial/invite that has since lapsed. */
    public function hasExpiredTrial(User $user, Community $community): bool
    {
        if ($community->owner_id === $user->id) {
            return false;
        }

        $member = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->first();

        return $member !== null
            && $member->membership_type === CommunityMember::MEMBERSHIP_FREE
            && $member->expires_at !== null
            && $member->expires_at->isPast();
    }

    private function isMemberExpired(CommunityMember $member): bool
    {
        return $member->expires_at !== null && $member->expires_at->isPast();
    }
}
