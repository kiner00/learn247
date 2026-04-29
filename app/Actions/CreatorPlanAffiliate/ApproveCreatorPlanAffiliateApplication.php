<?php

namespace App\Actions\CreatorPlanAffiliate;

use App\Models\Affiliate;
use App\Models\CreatorPlanAffiliateApplication;
use App\Models\User;
use App\Support\AffiliateCodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveCreatorPlanAffiliateApplication
{
    /**
     * @throws ValidationException
     */
    public function execute(CreatorPlanAffiliateApplication $application, User $reviewer): Affiliate
    {
        if (! $application->isPending()) {
            throw ValidationException::withMessages([
                'application' => 'This application has already been reviewed.',
            ]);
        }

        return DB::transaction(function () use ($application, $reviewer) {
            $affiliate = Affiliate::firstOrCreate(
                [
                    'user_id' => $application->user_id,
                    'scope' => Affiliate::SCOPE_CREATOR_PLAN,
                ],
                [
                    'community_id' => null,
                    'code' => AffiliateCodeGenerator::generate(),
                    'status' => Affiliate::STATUS_ACTIVE,
                ]
            );

            $application->update([
                'status' => CreatorPlanAffiliateApplication::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            return $affiliate;
        });
    }
}
