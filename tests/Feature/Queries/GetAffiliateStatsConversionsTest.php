<?php

namespace Tests\Feature\Queries;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\CartEvent;
use App\Models\Community;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Queries\Affiliate\GetAffiliateStats;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetAffiliateStatsConversionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversions_returns_clicknamics_shape(): void
    {
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate = Affiliate::create([
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'code' => 'CK001',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $referred = User::factory()->create(['phone' => '+639170000000']);
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'referred_user_id' => $referred->id,
            'sale_amount' => 500,
            'platform_fee' => 75,
            'commission_amount' => 50,
            'creator_amount' => 375,
            'status' => AffiliateConversion::STATUS_PENDING,
            'is_lifetime' => true,
        ]);

        $result = (new GetAffiliateStats)->conversions(collect([$affiliate->id]), 'month');

        $this->assertCount(1, $result);
        $row = $result->first();

        $this->assertSame(sprintf('CV-%06d', $conversion->id), $row['reference']);
        $this->assertSame(50.0, $row['amount']);
        $this->assertSame($community->name.' subscription', $row['description']);
        $this->assertTrue($row['is_lifetime']);
        $this->assertEquals('+639170000000', $row['referred_phone']);
    }

    public function test_status_reflects_wallet_transaction_when_present(): void
    {
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate = Affiliate::create([
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'code' => 'CK002',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $conversion = AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'referred_user_id' => User::factory()->create()->id,
            'sale_amount' => 500,
            'platform_fee' => 75,
            'commission_amount' => 50,
            'creator_amount' => 375,
            'status' => AffiliateConversion::STATUS_PENDING,
        ]);

        $affiliateUser = User::find($affiliate->user_id);
        app(WalletService::class)->credit(
            $affiliateUser,
            $conversion,
            50,
            WalletTransaction::STATUS_PAID,
            now()->addDays(7),
        );

        $rows = (new GetAffiliateStats)->conversions(collect([$affiliate->id]), 'month');
        $this->assertEquals('paid', $rows->first()['status']);

        $tx = $conversion->walletTransactions()->where('type', WalletTransaction::TYPE_CREDIT)->first();
        app(WalletService::class)->transition($tx, WalletTransaction::STATUS_SETTLED);

        $rows = (new GetAffiliateStats)->conversions(collect([$affiliate->id]), 'month');
        $this->assertEquals('settled', $rows->first()['status']);
    }

    public function test_abandoned_cart_event_appears_as_pending_row(): void
    {
        $community = Community::factory()->create(['affiliate_commission_rate' => 10, 'price' => 999]);
        $affiliate = Affiliate::create([
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'code' => 'CK003',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $abandonedUser = User::factory()->create(['name' => 'Pending Pete', 'email' => 'pete@example.com']);

        CartEvent::create([
            'community_id' => $community->id,
            'user_id' => $abandonedUser->id,
            'email' => $abandonedUser->email,
            'event_type' => CartEvent::TYPE_ABANDONED,
            'reference_type' => 'subscription',
            'metadata' => ['affiliate_code' => 'CK003', 'amount' => 999],
        ]);

        $rows = (new GetAffiliateStats)->conversions(collect([$affiliate->id]), 'month');

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertEquals('pending', $row['status']);
        $this->assertStringStartsWith('CE-', $row['reference']);
        $this->assertEquals(999.0, $row['sale_amount']);
        $this->assertEquals(0.0, $row['amount']);
        $this->assertEquals('Pending Pete', $row['referred_name']);
    }

    public function test_abandoned_cart_for_other_affiliates_code_is_not_included(): void
    {
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate = Affiliate::create([
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'code' => 'CK004',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        CartEvent::create([
            'community_id' => $community->id,
            'user_id' => User::factory()->create()->id,
            'email' => 'someone@example.com',
            'event_type' => CartEvent::TYPE_ABANDONED,
            'reference_type' => 'subscription',
            'metadata' => ['affiliate_code' => 'NOT_MINE', 'amount' => 500],
        ]);

        $rows = (new GetAffiliateStats)->conversions(collect([$affiliate->id]), 'month');
        $this->assertCount(0, $rows);
    }

    public function test_payment_method_is_picked_up_from_payment_metadata(): void
    {
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate = Affiliate::create([
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'code' => 'CK005',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $referred = User::factory()->create();
        $sub = \App\Models\Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => $referred->id,
            'affiliate_id' => $affiliate->id,
        ]);
        $payment = \App\Models\Payment::create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $referred->id,
            'amount' => 500,
            'currency' => 'PHP',
            'status' => \App\Models\Payment::STATUS_PAID,
            'metadata' => ['payment_method' => 'gcash'],
            'paid_at' => now(),
        ]);

        AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'subscription_id' => $sub->id,
            'payment_id' => $payment->id,
            'referred_user_id' => $referred->id,
            'sale_amount' => 500,
            'platform_fee' => 75,
            'commission_amount' => 50,
            'creator_amount' => 375,
            'status' => AffiliateConversion::STATUS_PENDING,
        ]);

        $rows = (new GetAffiliateStats)->conversions(collect([$affiliate->id]), 'month');
        $this->assertEquals('gcash', $rows->first()['payment_method']);
    }
}
