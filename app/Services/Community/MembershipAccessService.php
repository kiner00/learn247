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

        if ($community->isFree()) {
            return CommunityMember::where('community_id', $community->id)
                ->where('user_id', $user->id)
                ->exists();
        }

        return Subscription::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }

    public function assertMembership(User $user, Community $community): void
    {
        if ($community->owner_id === $user->id) {
            return;
        }

        if ($community->isFree()) {
            abort_unless(
                CommunityMember::where('community_id', $community->id)->where('user_id', $user->id)->exists(),
                403,
                'You must be a member of this community.'
            );
            return;
        }

        abort_unless(
            Subscription::where('community_id', $community->id)
                ->where('user_id', $user->id)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists(),
            403,
            'An active membership is required.'
        );
    }
}
