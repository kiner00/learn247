<?php

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class RequestManualKycReview
{
    public function execute(User $user): User
    {
        if (($user->kyc_ai_rejections ?? 0) < 3) {
            throw ValidationException::withMessages(['kyc' => 'Please re-submit your documents first.']);
        }

        if ($user->kyc_status === User::KYC_SUBMITTED) {
            throw ValidationException::withMessages(['kyc' => 'Your KYC is already under review.']);
        }

        $user->update([
            'kyc_status' => User::KYC_SUBMITTED,
            'kyc_submitted_at' => now(),
            'kyc_rejected_reason' => null,
        ]);

        return $user->refresh();
    }
}
