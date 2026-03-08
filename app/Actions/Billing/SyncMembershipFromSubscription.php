<?php

namespace App\Actions\Billing;

use App\Models\Affiliate;
use App\Models\CommunityMember;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

class SyncMembershipFromSubscription
{
    /**
     * Grant or revoke community membership based on subscription status.
     * Active → ensure member row exists.
     * Inactive → remove member role (preserves admin/moderator).
     */
    public function execute(Subscription $subscription): void
    {
        $communityId = $subscription->community_id;
        $userId      = $subscription->user_id;

        if ($subscription->isActive()) {
            CommunityMember::firstOrCreate(
                ['community_id' => $communityId, 'user_id' => $userId],
                ['role' => CommunityMember::ROLE_MEMBER, 'joined_at' => now()]
            );

            // Reactivate affiliate if they were previously suspended
            Affiliate::where('community_id', $communityId)
                ->where('user_id', $userId)
                ->where('status', Affiliate::STATUS_INACTIVE)
                ->update(['status' => Affiliate::STATUS_ACTIVE]);

            Log::info('Membership synced: member confirmed', compact('communityId', 'userId'));
        } else {
            CommunityMember::where('community_id', $communityId)
                ->where('user_id', $userId)
                ->where('role', CommunityMember::ROLE_MEMBER)
                ->delete();

            // Suspend affiliate — inactive subscribers cannot earn or receive payouts
            Affiliate::where('community_id', $communityId)
                ->where('user_id', $userId)
                ->where('status', Affiliate::STATUS_ACTIVE)
                ->update(['status' => Affiliate::STATUS_INACTIVE]);

            Log::info('Membership synced: member removed (inactive subscription)', [
                'community_id' => $communityId,
                'user_id'      => $userId,
                'status'       => $subscription->status,
            ]);
        }
    }
}
