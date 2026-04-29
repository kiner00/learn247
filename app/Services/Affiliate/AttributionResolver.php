<?php

namespace App\Services\Affiliate;

use App\Models\Affiliate;
use App\Models\AffiliateAttribution;
use App\Models\Community;

class AttributionResolver
{
    /**
     * Decide which affiliate should be credited for a purchase by $referredUserId
     * inside $community, given the affiliate that came from the click cookie / signup form.
     *
     * If the community has lifetime_attribution enabled and a prior attribution exists
     * for this user, the prior affiliate wins. Otherwise the last-touch affiliate is used,
     * and a fresh attribution row is recorded for future lifetime lookups.
     *
     * @return array{affiliate: ?Affiliate, is_lifetime: bool}
     */
    public function resolve(Community $community, ?int $referredUserId, ?Affiliate $lastTouch): array
    {
        if (! $referredUserId) {
            return ['affiliate' => $lastTouch, 'is_lifetime' => false];
        }

        if (! $community->lifetime_attribution) {
            return ['affiliate' => $lastTouch, 'is_lifetime' => false];
        }

        $existing = AffiliateAttribution::where('community_id', $community->id)
            ->where('referred_user_id', $referredUserId)
            ->first();

        if ($existing) {
            return [
                'affiliate' => $existing->affiliate,
                'is_lifetime' => true,
            ];
        }

        if ($lastTouch) {
            AffiliateAttribution::firstOrCreate(
                ['community_id' => $community->id, 'referred_user_id' => $referredUserId],
                ['affiliate_id' => $lastTouch->id],
            );
        }

        return ['affiliate' => $lastTouch, 'is_lifetime' => false];
    }
}
