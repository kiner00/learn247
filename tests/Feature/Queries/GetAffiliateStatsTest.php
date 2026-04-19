<?php

namespace Tests\Feature\Queries;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Queries\Affiliate\GetAffiliateStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetAffiliateStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_affiliates_returns_user_affiliates(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        Affiliate::create([
            'user_id' => $user->id,
            'community_id' => $community->id,
            'code' => 'STAT001',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $query = new GetAffiliateStats;
        $result = $query->getAffiliates($user);

        $this->assertCount(1, $result);
        $this->assertEquals('STAT001', $result->first()->code);
    }

    public function test_summary_calculates_totals(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate = Affiliate::create([
            'user_id' => $user->id,
            'community_id' => $community->id,
            'code' => 'STAT002',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $sub = Subscription::create([
            'community_id' => $community->id,
            'user_id' => User::factory()->create()->id,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);
        $payment = Payment::create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $sub->user_id,
            'amount' => 500,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'metadata' => [],
            'paid_at' => now(),
        ]);

        AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'subscription_id' => $sub->id,
            'payment_id' => $payment->id,
            'referred_user_id' => $sub->user_id,
            'sale_amount' => 500,
            'platform_fee' => 75,
            'commission_amount' => 50,
            'creator_amount' => 375,
            'status' => AffiliateConversion::STATUS_PENDING,
        ]);

        $query = new GetAffiliateStats;
        $result = $query->summary(collect([$affiliate->id]), 'month');

        $this->assertEquals(50, $result['total_earned']);
        $this->assertEquals(0, $result['total_paid']);
        $this->assertEquals(50, $result['total_pending']);
        $this->assertEquals(1, $result['total_conversions']);
    }

    public function test_summary_with_paid_conversions(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate = Affiliate::create([
            'user_id' => $user->id,
            'community_id' => $community->id,
            'code' => 'STAT003',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $sub = Subscription::create([
            'community_id' => $community->id,
            'user_id' => User::factory()->create()->id,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);
        $payment = Payment::create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $sub->user_id,
            'amount' => 500,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'metadata' => [],
            'paid_at' => now(),
        ]);

        AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'subscription_id' => $sub->id,
            'payment_id' => $payment->id,
            'referred_user_id' => $sub->user_id,
            'sale_amount' => 500,
            'platform_fee' => 75,
            'commission_amount' => 50,
            'creator_amount' => 375,
            'status' => AffiliateConversion::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $query = new GetAffiliateStats;
        $result = $query->summary(collect([$affiliate->id]), 'month');

        $this->assertEquals(50, $result['total_paid']);
    }

    public function test_conversions_returns_formatted_list(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate = Affiliate::create([
            'user_id' => $user->id,
            'community_id' => $community->id,
            'code' => 'STAT004',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $sub = Subscription::create([
            'community_id' => $community->id,
            'user_id' => User::factory()->create()->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        $payment = Payment::create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $sub->user_id,
            'amount' => 500,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'metadata' => [],
            'paid_at' => now(),
        ]);

        AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'subscription_id' => $sub->id,
            'payment_id' => $payment->id,
            'referred_user_id' => $sub->user_id,
            'sale_amount' => 500,
            'platform_fee' => 75,
            'commission_amount' => 50,
            'creator_amount' => 375,
            'status' => AffiliateConversion::STATUS_PENDING,
        ]);

        $query = new GetAffiliateStats;
        $result = $query->conversions(collect([$affiliate->id]), 'month');

        $this->assertCount(1, $result);
        $this->assertEquals(500, $result->first()['sale_amount']);
        $this->assertEquals(50, $result->first()['commission_amount']);
        $this->assertEquals($community->name, $result->first()['community']);
    }

    public function test_summary_with_different_periods(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate = Affiliate::create([
            'user_id' => $user->id,
            'community_id' => $community->id,
            'code' => 'STAT005',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $query = new GetAffiliateStats;

        $weekResult = $query->summary(collect([$affiliate->id]), 'week');
        $yearResult = $query->summary(collect([$affiliate->id]), 'year');
        $allResult = $query->summary(collect([$affiliate->id]), 'all');

        $this->assertEquals(0, $weekResult['total_conversions']);
        $this->assertEquals(0, $yearResult['total_conversions']);
        $this->assertEquals(0, $allResult['total_conversions']);
    }
}
