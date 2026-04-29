<?php

namespace App\Actions\CreatorPlanAffiliate;

use App\Models\Affiliate;
use App\Models\CreatorPlanAffiliateApplication;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ApplyForCreatorPlanAffiliate
{
    /**
     * @throws ValidationException
     */
    public function execute(User $user, ?string $pitch = null): CreatorPlanAffiliateApplication
    {
        $alreadyAffiliate = Affiliate::creatorPlan()
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyAffiliate) {
            throw ValidationException::withMessages([
                'application' => 'You are already a Creator Plan affiliate.',
            ]);
        }

        $hasPending = CreatorPlanAffiliateApplication::pending()
            ->where('user_id', $user->id)
            ->exists();

        if ($hasPending) {
            throw ValidationException::withMessages([
                'application' => 'You already have a pending application under review.',
            ]);
        }

        return CreatorPlanAffiliateApplication::create([
            'user_id' => $user->id,
            'status' => CreatorPlanAffiliateApplication::STATUS_PENDING,
            'pitch' => $pitch,
        ]);
    }
}
