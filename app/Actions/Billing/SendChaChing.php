<?php

namespace App\Actions\Billing;

use App\Mail\AffiliateChaChing;
use App\Mail\CreatorChaChing;
use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendChaChing
{
    /**
     * Send affiliate and creator cha-ching notification emails.
     *
     * @param  User|null  $affiliateUser  The affiliate who referred the sale (null if no affiliate)
     * @param  User|null  $creator        The community/course creator
     * @param  Community  $community
     * @param  float      $saleAmount
     * @param  float|null $commission     Affiliate commission amount (null if no affiliate)
     * @param  string|null $referredBy    Name of the referrer for creator email (null if no affiliate)
     */
    public function execute(
        ?User $affiliateUser,
        ?User $creator,
        Community $community,
        float $saleAmount,
        ?float $commission,
        ?string $referredBy,
    ): void {
        try {
            if ($affiliateUser && $commission !== null) {
                Mail::to($affiliateUser->email)->queue(
                    new AffiliateChaChing($affiliateUser, $community, $saleAmount, $commission)
                );
            }
            if ($creator) {
                Mail::to($creator->email)->queue(
                    new CreatorChaChing($creator, $community, $saleAmount, $referredBy)
                );
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send cha-ching email', ['error' => $e->getMessage()]);
        }
    }
}
