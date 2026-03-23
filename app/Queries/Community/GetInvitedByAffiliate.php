<?php

namespace App\Queries\Community;

use App\Models\Affiliate;
use App\Models\Community;

class GetInvitedByAffiliate
{
    /**
     * Returns the `invitedBy` array for a ref code, or null if not applicable.
     */
    public function execute(Community $community, ?string $refCode): ?array
    {
        if (! $refCode) {
            return null;
        }

        $affiliate = Affiliate::where('code', $refCode)
            ->where('community_id', $community->id)
            ->where('status', Affiliate::STATUS_ACTIVE)
            ->with('user:id,name,avatar')
            ->first();

        if (! $affiliate) {
            return null;
        }

        return [
            'name'                => $affiliate->user->name,
            'avatar'              => $affiliate->user->avatar,
            'code'                => $refCode,
            'facebook_pixel_id'   => $affiliate->facebook_pixel_id,
            'tiktok_pixel_id'     => $affiliate->tiktok_pixel_id,
            'google_analytics_id' => $affiliate->google_analytics_id,
        ];
    }
}
