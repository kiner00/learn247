<?php

namespace App\Actions\Affiliate;

use App\Models\Affiliate;
use App\Models\Community;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JoinAffiliate
{
    /**
     * @throws ValidationException
     */
    public function execute(User $user, Community $community): Affiliate
    {
        if (! $community->hasAffiliateProgram()) {
            throw ValidationException::withMessages([
                'affiliate' => 'This community does not have an affiliate program.',
            ]);
        }

        $isSubscribed = Subscription::where('user_id', $user->id)
            ->where('community_id', $community->id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('expires_at', '>', now())
            ->exists();

        if (! $isSubscribed) {
            throw ValidationException::withMessages([
                'affiliate' => 'You must be subscribed to this community to become an affiliate.',
            ]);
        }

        if (Affiliate::where('community_id', $community->id)->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'affiliate' => 'You are already an affiliate for this community.',
            ]);
        }

        // Generate a unique code (retry on collision)
        do {
            $code = Str::random(12);
        } while (Affiliate::where('code', $code)->exists());

        return Affiliate::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'code'         => $code,
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);
    }
}
