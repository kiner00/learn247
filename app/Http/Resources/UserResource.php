<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'email_verified' => $this->hasVerifiedEmail(),
            'email_verified_at' => $this->email_verified_at,
            'kyc_status' => $this->kyc_status ?? \App\Models\User::KYC_NONE,
            'kyc_verified' => $this->isKycVerified(),
            'created_at' => $this->created_at,
        ];
    }
}
