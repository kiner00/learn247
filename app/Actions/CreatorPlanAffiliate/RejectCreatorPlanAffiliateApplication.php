<?php

namespace App\Actions\CreatorPlanAffiliate;

use App\Models\CreatorPlanAffiliateApplication;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RejectCreatorPlanAffiliateApplication
{
    /**
     * @throws ValidationException
     */
    public function execute(CreatorPlanAffiliateApplication $application, User $reviewer, string $reason): CreatorPlanAffiliateApplication
    {
        if (! $application->isPending()) {
            throw ValidationException::withMessages([
                'application' => 'This application has already been reviewed.',
            ]);
        }

        $application->update([
            'status' => CreatorPlanAffiliateApplication::STATUS_REJECTED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $application->fresh();
    }
}
