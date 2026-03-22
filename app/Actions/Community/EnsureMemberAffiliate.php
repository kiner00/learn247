<?php

namespace App\Actions\Community;

use App\Models\Affiliate;
use App\Models\Community;
use App\Models\Subscription;
use Illuminate\Support\Str;

class EnsureMemberAffiliate
{
    /**
     * Auto-provision an affiliate record for active subscribers who don't have one yet.
     * Returns the existing or newly created Affiliate, or null if not eligible.
     */
    public function execute(Community $community, int $userId): ?Affiliate
    {
        $existing = $community->affiliates()->where('user_id', $userId)->first();

        if ($existing) {
            return $existing;
        }

        $isActiveSubscriber = Subscription::where('user_id', $userId)
            ->where('community_id', $community->id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('expires_at', '>', now())
            ->exists();

        if (! $isActiveSubscriber) {
            return null;
        }

        do {
            $code = Str::random(12);
        } while (Affiliate::where('code', $code)->exists());

        return Affiliate::create([
            'community_id' => $community->id,
            'user_id'      => $userId,
            'code'         => $code,
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);
    }
}
