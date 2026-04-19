<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\KycVerificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class VerifyKycDocuments implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public User $user) {}

    public function handle(KycVerificationService $service): void
    {
        // Only process if still in submitted status
        if ($this->user->kyc_status !== User::KYC_SUBMITTED) {
            return;
        }

        $result = $service->verifyAndUpdate($this->user);

        // If AI didn't auto-approve, leave as "submitted" for manual admin review
        // The admin can still approve/reject from the KYC Reviews page
        if (! ($result['approved'] ?? false)) {
            \Illuminate\Support\Facades\Log::info('KYC not auto-approved, pending manual review', [
                'user_id' => $this->user->id,
                'reason' => $result['reason'] ?? 'unknown',
            ]);
        }
    }
}
