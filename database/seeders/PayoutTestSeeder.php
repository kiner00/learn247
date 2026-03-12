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

class PayoutTestSeeder extends Seeder
{
    private const ACCOUNTS = [
        [
            'email'          => 'kinermercurio@gmail.com',
            'name'           => 'Kiner Mercurio',
            'username'       => 'kinermercurio',
            'community_name' => 'Kiner Test Community',
            'payout_method'  => 'gcash',
            'payout_details' => '09171234567', // replace with real GCash number
        ],
        [
            'email'          => 'clivellora05@gmail.com',
            'name'           => 'Clive Llora',
            'username'       => 'clivellora',
            'community_name' => 'Clive Test Community',
            'payout_method'  => 'gcash',
            'payout_details' => '09177654321', // replace with real GCash number
        ],
    ];

    public function run(): void
    {
        abort_unless(app()->isLocal(), 403, 'PayoutTestSeeder must only run in local environment.');

        foreach (self::ACCOUNTS as $account) {
            $user = User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name'     => $account['name'],
                    'username' => $account['username'],
                    'password' => Hash::make('password'),
                ]
            );

            // Set payout details on the user (for owner payout)
            $user->update([
                'payout_method'  => $account['payout_method'],
                'payout_details' => $account['payout_details'],
            ]);

            // ── Community ────────────────────────────────────────────────────────
            $slug      = Str::slug($account['community_name']);
            $community = Community::firstOrCreate(
                ['slug' => $slug],
                [
                    'name'        => $account['community_name'],
                    'owner_id'    => $user->id,
                    'description' => 'Dev payout test community.',
                    'category'    => 'Business',
                    'price'       => 499,
                    'currency'    => 'PHP',
                    'is_private'  => false,
                ]
            );

            // Ensure owner is a community member
            CommunityMember::firstOrCreate(
                ['community_id' => $community->id, 'user_id' => $user->id],
                ['role' => CommunityMember::ROLE_ADMIN, 'joined_at' => now()->subMonths(2)]
            );

            // ── 3 paid payments, all 16+ days old (eligible for owner payout) ───
            for ($i = 1; $i <= 3; $i++) {
                $subscriber = User::firstOrCreate(
                    ['email' => "payout-test-sub-{$user->id}-{$i}@test.com"],
                    [
                        'name'     => "Test Subscriber {$i}",
                        'username' => "payout-test-sub-{$user->id}-{$i}",
                        'password' => Hash::make('password'),
                    ]
                );

                $subscription = Subscription::firstOrCreate(
                    ['community_id' => $community->id, 'user_id' => $subscriber->id],
                    [
                        'status'             => Subscription::STATUS_ACTIVE,
                        'xendit_id'          => 'dev_payout_' . Str::uuid(),
                        'xendit_invoice_url' => 'https://checkout.xendit.co/dev',
                        'expires_at'         => now()->addDays(30),
                    ]
                );

                Payment::firstOrCreate(
                    ['subscription_id' => $subscription->id, 'xendit_event_id' => "dev_payout_{$user->id}_{$i}_PAID"],
                    [
                        'community_id'       => $community->id,
                        'user_id'            => $subscriber->id,
                        'amount'             => 499,
                        'currency'           => 'PHP',
                        'status'             => Payment::STATUS_PAID,
                        'provider_reference' => 'dev_ref_' . Str::random(8),
                        'metadata'           => [],
                        'paid_at'            => now()->subDays(20), // 20 days old → eligible
                    ]
                );

                CommunityMember::firstOrCreate(
                    ['community_id' => $community->id, 'user_id' => $subscriber->id],
                    ['role' => CommunityMember::ROLE_MEMBER, 'joined_at' => now()->subDays(20)]
                );
            }

            // ── Affiliate record for the user (for affiliate payout) ─────────────
            $code = Str::upper(Str::random(10));
            while (Affiliate::where('code', $code)->exists()) {
                $code = Str::upper(Str::random(10));
            }

            $affiliate = Affiliate::firstOrCreate(
                ['community_id' => $community->id, 'user_id' => $user->id],
                [
                    'code'           => $code,
                    'status'         => Affiliate::STATUS_ACTIVE,
                    'payout_method'  => $account['payout_method'],
                    'payout_details' => $account['payout_details'],
                ]
            );

            // Ensure payout details are set on affiliate
            $affiliate->update([
                'payout_method'  => $account['payout_method'],
                'payout_details' => $account['payout_details'],
            ]);

            // ── 2 affiliate conversions, 16+ days old (eligible for affiliate payout) ──
            for ($i = 1; $i <= 2; $i++) {
                $referred = User::firstOrCreate(
                    ['email' => "payout-test-ref-{$user->id}-{$i}@test.com"],
                    [
                        'name'     => "Test Referred {$i}",
                        'username' => "payout-test-ref-{$user->id}-{$i}",
                        'password' => Hash::make('password'),
                    ]
                );

                $refSub = Subscription::firstOrCreate(
                    ['community_id' => $community->id, 'user_id' => $referred->id],
                    [
                        'status'             => Subscription::STATUS_ACTIVE,
                        'xendit_id'          => 'dev_ref_' . Str::uuid(),
                        'xendit_invoice_url' => 'https://checkout.xendit.co/dev',
                        'expires_at'         => now()->addDays(30),
                        'affiliate_id'       => $affiliate->id,
                    ]
                );

                $refPayment = Payment::firstOrCreate(
                    ['subscription_id' => $refSub->id, 'xendit_event_id' => "dev_ref_{$user->id}_{$i}_PAID"],
                    [
                        'community_id'       => $community->id,
                        'user_id'            => $referred->id,
                        'amount'             => 499,
                        'currency'           => 'PHP',
                        'status'             => Payment::STATUS_PAID,
                        'provider_reference' => 'dev_ref_' . Str::random(8),
                        'metadata'           => [],
                        'paid_at'            => now()->subDays(20),
                    ]
                );

                // AffiliateConversion with created_at backdated so it's eligible
                $existing = AffiliateConversion::where('affiliate_id', $affiliate->id)
                    ->where('payment_id', $refPayment->id)
                    ->first();

                if (! $existing) {
                    $conversion = AffiliateConversion::create([
                        'affiliate_id'      => $affiliate->id,
                        'subscription_id'   => $refSub->id,
                        'payment_id'        => $refPayment->id,
                        'referred_user_id'  => $referred->id,
                        'sale_amount'       => 499,
                        'platform_fee'      => round(499 * 0.15, 2),
                        'commission_amount' => round(499 * 0.20, 2), // 20% commission
                        'creator_amount'    => round(499 * 0.65, 2),
                        'status'            => AffiliateConversion::STATUS_PENDING,
                    ]);

                    // Backdate so it passes the 15-day eligibility check
                    $conversion->created_at = now()->subDays(20);
                    $conversion->save();
                }
            }

            $this->command->info("✓ {$account['email']} — community: {$community->name}, payments: 3 (₱1,497 eligible), affiliate conversions: 2 (₱199.60 eligible)");
        }

        $this->command->newLine();
        $this->command->warn('NOTE: Update payout_details in PayoutTestSeeder with real GCash/Maya numbers before testing live disbursement.');
    }
}
