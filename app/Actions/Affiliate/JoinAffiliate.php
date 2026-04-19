<?php

namespace App\Actions\Affiliate;

use App\Models\Affiliate;
use App\Models\Community;
use App\Models\User;
use App\Support\AffiliateCodeGenerator;
use App\Support\AffiliateSubscriptionChecker;
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

        if (! AffiliateSubscriptionChecker::isActivelySubscribed($user->id, $community->id)) {
            throw ValidationException::withMessages([
                'affiliate' => 'You must be subscribed to this community to become an affiliate.',
            ]);
        }

        if (Affiliate::where('community_id', $community->id)->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'affiliate' => 'You are already an affiliate for this community.',
            ]);
        }

        return Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'code' => AffiliateCodeGenerator::generate(),
            'status' => Affiliate::STATUS_ACTIVE,
        ]);
    }
}
