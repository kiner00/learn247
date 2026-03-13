<?php

namespace Database\Seeders;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AffiliatePayoutTestSeeder extends Seeder
{
    /**
     * Seed affiliate members who joined via referral link, shared their own
     * invite URL, and now have pending affiliate commissions to be paid out.
     *
     * Each affiliate below:
     *   - Is a paid member of the target community
     *   - Has their own affiliate code (as if auto-created after joining)
     *   - Has referred 2 people → 2 AffiliateConversion records (20% of ₱499 = ₱99.80 each)
     *   - total_earned = ₱199.60, total_paid = ₱0  → ₱199.60 pending
     */
    private const COMMUNITY_SLUG = 'dropshipping-academy-ph'; // existing paid community (₱499)

    private const AFFILIATES = [
        [
            'email'          => 'affiliate-member-1@test.com',
            'name'           => 'Ana Reyes',
            'username'       => 'anareyes_aff',
            'payout_method'  => 'gcash',
            'payout_details' => '09181111111',
        ],
        [
            'email'          => 'affiliate-member-2@test.com',
            'name'           => 'Marco Santos',
            'username'       => 'marcosantos_aff',
            'payout_method'  => 'gcash',
            'payout_details' => '09182222222',
        ],
        [
            'email'          => 'affiliate-member-3@test.com',
            'name'           => 'Joy Cruz',
            'username'       => 'joycruz_aff',
            'payout_method'  => 'maya',
            'payout_details' => '09183333333',
        ],
    ];

    public function run(): void
    {
        abort_unless(app()->isLocal(), 403, 'AffiliatePayoutTestSeeder must only run in local environment.');

        $community = Community::where('slug', self::COMMUNITY_SLUG)->first();

        if (! $community) {
            $this->command->error('Community "' . self::COMMUNITY_SLUG . '" not found. Run PayoutTestSeeder first, or update COMMUNITY_SLUG.');
            return;
        }

        $communityPrice = (float) $community->price ?: 499;

        foreach (self::AFFILIATES as $index => $account) {
            // ── Affiliate user (joined the community) ────────────────────────────
            $affiliateUser = User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name'              => $account['name'],
                    'username'          => $account['username'],
                    'password'          => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            // Subscription + payment for the affiliate user themselves joining
            $ownSub = Subscription::firstOrCreate(
                ['community_id' => $community->id, 'user_id' => $affiliateUser->id],
                [
                    'status'             => Subscription::STATUS_ACTIVE,
                    'xendit_id'          => 'dev_aff_own_' . Str::uuid(),
                    'xendit_invoice_url' => 'https://checkout.xendit.co/dev',
                    'expires_at'         => now()->addDays(30),
                ]
            );

            Payment::firstOrCreate(
                ['subscription_id' => $ownSub->id, 'xendit_event_id' => "dev_aff_own_{$affiliateUser->id}_PAID"],
                [
                    'community_id'       => $community->id,
                    'user_id'            => $affiliateUser->id,
                    'amount'             => $communityPrice,
                    'currency'           => 'PHP',
                    'status'             => Payment::STATUS_PAID,
                    'provider_reference' => 'dev_ref_' . Str::random(8),
                    'metadata'           => [],
                    'paid_at'            => now()->subDays(25),
                ]
            );

            CommunityMember::firstOrCreate(
                ['community_id' => $community->id, 'user_id' => $affiliateUser->id],
                ['role' => CommunityMember::ROLE_MEMBER, 'joined_at' => now()->subDays(25)]
            );

            // ── Their own affiliate code ──────────────────────────────────────────
            $code = Str::upper(Str::random(10));
            while (Affiliate::where('code', $code)->exists()) {
                $code = Str::upper(Str::random(10));
            }

            $commissionRate = 0.20; // 20%
            $platformFeeRate = 0.15; // 15%

            $affiliate = Affiliate::firstOrCreate(
                ['community_id' => $community->id, 'user_id' => $affiliateUser->id],
                [
                    'code'           => $code,
                    'status'         => Affiliate::STATUS_ACTIVE,
                    'payout_method'  => $account['payout_method'],
                    'payout_details' => $account['payout_details'],
                ]
            );

            $affiliate->update([
                'payout_method'  => $account['payout_method'],
                'payout_details' => $account['payout_details'],
            ]);

            // ── 2 referred users who paid ─────────────────────────────────────────
            $totalEarned = 0;

            for ($i = 1; $i <= 2; $i++) {
                $referred = User::firstOrCreate(
                    ['email' => "aff-ref-{$affiliateUser->id}-{$i}@test.com"],
                    [
                        'name'     => "Referred by {$account['name']} #{$i}",
                        'username' => "ref-{$affiliateUser->id}-{$i}",
                        'password' => Hash::make('password'),
                    ]
                );

                $refSub = Subscription::firstOrCreate(
                    ['community_id' => $community->id, 'user_id' => $referred->id],
                    [
                        'status'             => Subscription::STATUS_ACTIVE,
                        'xendit_id'          => 'dev_aff_ref_' . Str::uuid(),
                        'xendit_invoice_url' => 'https://checkout.xendit.co/dev',
                        'expires_at'         => now()->addDays(30),
                        'affiliate_id'       => $affiliate->id,
                    ]
                );

                $refPayment = Payment::firstOrCreate(
                    ['subscription_id' => $refSub->id, 'xendit_event_id' => "dev_aff_ref_{$affiliateUser->id}_{$i}_PAID"],
                    [
                        'community_id'       => $community->id,
                        'user_id'            => $referred->id,
                        'amount'             => $communityPrice,
                        'currency'           => 'PHP',
                        'status'             => Payment::STATUS_PAID,
                        'provider_reference' => 'dev_ref_' . Str::random(8),
                        'metadata'           => [],
                        'paid_at'            => now()->subDays(20),
                    ]
                );

                CommunityMember::firstOrCreate(
                    ['community_id' => $community->id, 'user_id' => $referred->id],
                    ['role' => CommunityMember::ROLE_MEMBER, 'joined_at' => now()->subDays(20)]
                );

                $commission = round($communityPrice * $commissionRate, 2);
                $platformFee = round($communityPrice * $platformFeeRate, 2);
                $creatorAmount = round($communityPrice - $platformFee - $commission, 2);

                $existing = AffiliateConversion::where('affiliate_id', $affiliate->id)
                    ->where('payment_id', $refPayment->id)
                    ->first();

                if (! $existing) {
                    $conversion = AffiliateConversion::create([
                        'affiliate_id'      => $affiliate->id,
                        'subscription_id'   => $refSub->id,
                        'payment_id'        => $refPayment->id,
                        'referred_user_id'  => $referred->id,
                        'sale_amount'       => $communityPrice,
                        'platform_fee'      => $platformFee,
                        'commission_amount' => $commission,
                        'creator_amount'    => $creatorAmount,
                        'status'            => AffiliateConversion::STATUS_PENDING,
                    ]);

                    // Backdate so it passes the 15-day eligibility check
                    $conversion->created_at = now()->subDays(20);
                    $conversion->save();
                }

                $totalEarned += $commission;
            }

            // Sync total_earned on the affiliate record
            $affiliate->update(['total_earned' => $totalEarned, 'total_paid' => 0]);

            $this->command->info("✓ {$account['name']} ({$account['email']}) — code: {$affiliate->code}, referred: 2, earned: ₱{$totalEarned} pending");
        }

        $this->command->newLine();
        $this->command->warn('NOTE: Update payout_details in AffiliatePayoutTestSeeder with real GCash/Maya numbers before testing live disbursement.');
    }
}
