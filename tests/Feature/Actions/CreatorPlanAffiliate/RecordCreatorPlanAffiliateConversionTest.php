<?php

namespace Tests\Feature\Actions\CreatorPlanAffiliate;

use App\Actions\CreatorPlanAffiliate\RecordCreatorPlanAffiliateConversion;
use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\CreatorSubscription;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordCreatorPlanAffiliateConversionTest extends TestCase
{
    use RefreshDatabase;

    private RecordCreatorPlanAffiliateConversion $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = app(RecordCreatorPlanAffiliateConversion::class);
        Setting::set('creator_plan_affiliate_commission_rate', '20');
        Setting::set('creator_plan_affiliate_max_months', '12');
    }

    private function makeAffiliate(): Affiliate
    {
        return Affiliate::create([
            'user_id' => User::factory()->create()->id,
            'community_id' => null,
            'scope' => Affiliate::SCOPE_CREATOR_PLAN,
            'code' => 'CPA'.fake()->bothify('######'),
            'status' => Affiliate::STATUS_ACTIVE,
        ]);
    }

    private function makeCreatorSub(?Affiliate $affiliate, string $cycle = 'monthly'): CreatorSubscription
    {
        return CreatorSubscription::create([
            'user_id' => User::factory()->create()->id,
            'affiliate_id' => $affiliate?->id,
            'plan' => CreatorSubscription::PLAN_PRO,
            'billing_cycle' => $cycle,
            'status' => CreatorSubscription::STATUS_ACTIVE,
        ]);
    }

    private function makePayment(CreatorSubscription $sub, float $amount = 1999): Payment
    {
        return Payment::create([
            'user_id' => $sub->user_id,
            'amount' => $amount,
            'processing_fee' => 0,
            'platform_fee' => 0,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    public function test_creates_conversion_for_first_monthly_payment(): void
    {
        $affiliate = $this->makeAffiliate();
        $sub = $this->makeCreatorSub($affiliate, 'monthly');
        $payment = $this->makePayment($sub, 1999);

        $result = $this->action->execute($sub, $payment);

        $this->assertNotNull($result);
        $this->assertEquals(399.80, $result['commission']);
        $this->assertDatabaseHas('affiliate_conversions', [
            'creator_subscription_id' => $sub->id,
            'affiliate_id' => $affiliate->id,
            'commission_amount' => 399.80,
            'platform_fee' => 1599.20,
            'creator_amount' => 0,
            'billing_month_index' => 1,
        ]);
    }

    public function test_credits_wallet_with_pending_status(): void
    {
        $affiliate = $this->makeAffiliate();
        $sub = $this->makeCreatorSub($affiliate, 'monthly');
        $payment = $this->makePayment($sub);

        $this->action->execute($sub, $payment);

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $affiliate->user_id,
            'type' => WalletTransaction::TYPE_CREDIT,
            'amount' => 399.80,
        ]);
    }

    public function test_returns_null_if_no_affiliate_attached(): void
    {
        $sub = $this->makeCreatorSub(null, 'monthly');
        $payment = $this->makePayment($sub);

        $this->assertNull($this->action->execute($sub, $payment));
        $this->assertEquals(0, AffiliateConversion::count());
    }

    public function test_returns_null_if_affiliate_inactive(): void
    {
        $affiliate = $this->makeAffiliate();
        $affiliate->update(['status' => Affiliate::STATUS_INACTIVE]);
        $sub = $this->makeCreatorSub($affiliate, 'monthly');
        $payment = $this->makePayment($sub);

        $this->assertNull($this->action->execute($sub, $payment));
    }

    public function test_returns_null_if_affiliate_is_not_creator_plan_scope(): void
    {
        // Edge case: subscription somehow has a community-scoped affiliate attached
        $user = User::factory()->create();
        $community = \App\Models\Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate = Affiliate::create([
            'user_id' => $user->id,
            'community_id' => $community->id,
            'scope' => Affiliate::SCOPE_COMMUNITY,
            'code' => 'COMMA1',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);
        $sub = $this->makeCreatorSub($affiliate, 'monthly');
        $payment = $this->makePayment($sub);

        $this->assertNull($this->action->execute($sub, $payment));
    }

    public function test_caps_monthly_at_12_payments(): void
    {
        $affiliate = $this->makeAffiliate();
        $sub = $this->makeCreatorSub($affiliate, 'monthly');

        for ($i = 1; $i <= 12; $i++) {
            $this->action->execute($sub, $this->makePayment($sub));
        }

        $this->assertEquals(12, AffiliateConversion::where('creator_subscription_id', $sub->id)->count());

        // 13th payment should be a no-op
        $thirteenth = $this->makePayment($sub);
        $this->assertNull($this->action->execute($sub, $thirteenth));
        $this->assertEquals(12, AffiliateConversion::where('creator_subscription_id', $sub->id)->count());
    }

    public function test_annual_creates_only_one_conversion(): void
    {
        $affiliate = $this->makeAffiliate();
        $sub = $this->makeCreatorSub($affiliate, 'annual');

        $this->action->execute($sub, $this->makePayment($sub, 19990));
        // Even if a second payment somehow arrives, no second conversion
        $this->assertNull($this->action->execute($sub, $this->makePayment($sub, 19990)));

        $this->assertEquals(1, AffiliateConversion::where('creator_subscription_id', $sub->id)->count());
    }

    public function test_idempotent_for_same_payment(): void
    {
        $affiliate = $this->makeAffiliate();
        $sub = $this->makeCreatorSub($affiliate, 'monthly');
        $payment = $this->makePayment($sub);

        $this->action->execute($sub, $payment);
        $this->action->execute($sub, $payment);

        $this->assertEquals(1, AffiliateConversion::where('creator_subscription_id', $sub->id)->count());
    }

    public function test_increments_billing_month_index(): void
    {
        $affiliate = $this->makeAffiliate();
        $sub = $this->makeCreatorSub($affiliate, 'monthly');

        $this->action->execute($sub, $this->makePayment($sub));
        $this->action->execute($sub, $this->makePayment($sub));
        $this->action->execute($sub, $this->makePayment($sub));

        $indices = AffiliateConversion::where('creator_subscription_id', $sub->id)
            ->orderBy('id')
            ->pluck('billing_month_index')
            ->all();

        $this->assertEquals([1, 2, 3], $indices);
    }

    public function test_increments_total_earned_on_affiliate(): void
    {
        $affiliate = $this->makeAffiliate();
        $sub = $this->makeCreatorSub($affiliate, 'monthly');

        $this->action->execute($sub, $this->makePayment($sub, 1000));
        $this->action->execute($sub, $this->makePayment($sub, 1000));

        $this->assertEquals(400.00, (float) $affiliate->fresh()->total_earned);
    }

    public function test_uses_admin_configured_rate(): void
    {
        Setting::set('creator_plan_affiliate_commission_rate', '30');
        $affiliate = $this->makeAffiliate();
        $sub = $this->makeCreatorSub($affiliate, 'monthly');
        $payment = $this->makePayment($sub, 1000);

        $result = $this->action->execute($sub, $payment);

        $this->assertEquals(300.00, $result['commission']);
    }
}
