<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class KycResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->kyc_status ?? User::KYC_NONE,
            'verified' => $this->isKycVerified(),
            'verified_at' => $this->kyc_verified_at,
            'submitted_at' => $this->kyc_submitted_at,
            'rejected_reason' => $this->kyc_rejected_reason,
            'ai_rejections' => (int) ($this->kyc_ai_rejections ?? 0),
            'can_request_manual_review' => ($this->kyc_ai_rejections ?? 0) >= 3
                && $this->kyc_status !== User::KYC_SUBMITTED
                && $this->kyc_status !== User::KYC_APPROVED,
        ];
    }
}
