<?php

namespace Tests\Feature\Actions\Payout;

use App\Actions\Payout\RequestAffiliatePayout;
use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\PayoutRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Queries\Payout\CalculateEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RequestAffiliatePayoutTest extends TestCase
{
    use RefreshDatabase;

    private function makeAffiliate(array $overrides = []): Affiliate
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();

        return Affiliate::create(array_merge([
            'community_id'   => $community->id,
            'user_id'        => $user->id,
            'code'           => 'AFF-TEST-' . uniqid(),
            'status'         => Affiliate::STATUS_ACTIVE,
            'payout_method'  => 'gcash',
            'payout_details' => '09171234567',
            'total_earned'   => 500,
            'total_paid'     => 0,
        ], $overrides));
    }

    public function test_inactive_affiliate_returns_failure(): void
    {
        $affiliate = $this->makeAffiliate(['status' => Affiliate::STATUS_INACTIVE]);

        $eligibility = Mockery::mock(CalculateEligibility::class);
        $action = new RequestAffiliatePayout($eligibility);

        $result = $action->execute($affiliate, 100);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('suspended', $result['message']);
    }

    public function test_missing_payout_method_returns_failure(): void
    {
        $affiliate = $this->makeAffiliate([
            'payout_method'  => null,
            'payout_details' => null,
        ]);

        $eligibility = Mockery::mock(CalculateEligibility::class);
        $action = new RequestAffiliatePayout($eligibility);

        $result = $action->execute($affiliate, 100);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('payout method', $result['message']);
    }

    public function test_unsupported_payout_method_returns_failure(): void
    {
        $affiliate = $this->makeAffiliate([
            'payout_method'  => 'paypal',
            'payout_details' => 'test@paypal.com',
        ]);

        $eligibility = Mockery::mock(CalculateEligibility::class);
        $action = new RequestAffiliatePayout($eligibility);

        $result = $action->execute($affiliate, 100);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('payout method', $result['message']);
    }

    public function test_has_pending_payout_request_returns_failure(): void
    {
        $affiliate = $this->makeAffiliate();

        PayoutRequest::create([
            'user_id'         => $affiliate->user_id,
            'type'            => PayoutRequest::TYPE_AFFILIATE,
            'community_id'    => $affiliate->community_id,
            'affiliate_id'    => $affiliate->id,
            'amount'          => 100,
            'eligible_amount' => 100,
            'status'          => PayoutRequest::STATUS_PENDING,
        ]);

        $eligibility = Mockery::mock(CalculateEligibility::class);
        $action = new RequestAffiliatePayout($eligibility);

        $result = $action->execute($affiliate, 100);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already have an active payout', $result['message']);
    }

    public function test_zero_eligible_earnings_returns_failure(): void
    {
        $affiliate = $this->makeAffiliate();

        $eligibility = Mockery::mock(CalculateEligibility::class);
        $eligibility->shouldReceive('forAffiliate')->with($affiliate)->andReturn(0.0);

        $action = new RequestAffiliatePayout($eligibility);
        $result = $action->execute($affiliate, 100);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No eligible earnings', $result['message']);
    }

    public function test_amount_exceeds_eligible_balance_returns_failure(): void
    {
        $affiliate = $this->makeAffiliate();

        $eligibility = Mockery::mock(CalculateEligibility::class);
        $eligibility->shouldReceive('forAffiliate')->with($affiliate)->andReturn(50.0);

        $action = new RequestAffiliatePayout($eligibility);
        $result = $action->execute($affiliate, 100);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('exceeds eligible balance', $result['message']);
    }

    public function test_successful_payout_request_creates_record(): void
    {
        $affiliate = $this->makeAffiliate();

        $eligibility = Mockery::mock(CalculateEligibility::class);
        $eligibility->shouldReceive('forAffiliate')->with($affiliate)->andReturn(200.0);

        $action = new RequestAffiliatePayout($eligibility);
        $result = $action->execute($affiliate, 150);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('submitted', $result['message']);
        $this->assertDatabaseHas('payout_requests', [
            'user_id'      => $affiliate->user_id,
            'type'         => PayoutRequest::TYPE_AFFILIATE,
            'affiliate_id' => $affiliate->id,
            'amount'       => 150,
            'status'       => PayoutRequest::STATUS_PENDING,
        ]);
    }
}
