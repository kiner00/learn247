<?php

namespace Tests\Feature\Actions\Payout;

use App\Actions\Payout\RequestOwnerPayout;
use App\Models\Community;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Queries\Payout\CalculateEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RequestOwnerPayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_payout_method_returns_failure(): void
    {
        $owner = User::factory()->create(['payout_method' => null, 'payout_details' => null, 'kyc_verified_at' => now()]);
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $eligibility = Mockery::mock(CalculateEligibility::class);
        $action = new RequestOwnerPayout($eligibility, app(\App\Services\Wallet\WalletService::class));

        $result = $action->execute($owner, $community, 100);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('payout method', $result['message']);
    }

    public function test_unverified_kyc_returns_failure(): void
    {
        $owner = User::factory()->create([
            'payout_method' => 'gcash',
            'payout_details' => '09171234567',
            'kyc_verified_at' => null,
            'kyc_status' => User::KYC_NONE,
        ]);
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $eligibility = Mockery::mock(CalculateEligibility::class);
        $action = new RequestOwnerPayout($eligibility, app(\App\Services\Wallet\WalletService::class));

        $result = $action->execute($owner, $community, 100);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('KYC verification is required', $result['message']);
    }

    public function test_unsupported_payout_method_returns_failure(): void
    {
        $owner = User::factory()->create(['payout_method' => 'paypal', 'payout_details' => 'x@paypal.com', 'kyc_verified_at' => now()]);
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $eligibility = Mockery::mock(CalculateEligibility::class);
        $action = new RequestOwnerPayout($eligibility, app(\App\Services\Wallet\WalletService::class));

        $result = $action->execute($owner, $community, 100);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('payout method', $result['message']);
    }

    public function test_has_pending_request_returns_failure(): void
    {
        $owner = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567', 'kyc_verified_at' => now()]);
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        PayoutRequest::create([
            'user_id' => $owner->id,
            'type' => PayoutRequest::TYPE_OWNER,
            'community_id' => $community->id,
            'amount' => 100,
            'eligible_amount' => 100,
            'status' => PayoutRequest::STATUS_PENDING,
        ]);

        $eligibility = Mockery::mock(CalculateEligibility::class);
        $action = new RequestOwnerPayout($eligibility, app(\App\Services\Wallet\WalletService::class));

        $result = $action->execute($owner, $community, 100);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already have a pending or approved payout', $result['message']);
    }

    public function test_zero_eligible_earnings_returns_failure(): void
    {
        $owner = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567', 'kyc_verified_at' => now()]);
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $eligibility = Mockery::mock(CalculateEligibility::class);
        $eligibility->shouldReceive('forOwner')->with($community)->andReturn([0.0, 0.0, null]);

        $action = new RequestOwnerPayout($eligibility, app(\App\Services\Wallet\WalletService::class));
        $result = $action->execute($owner, $community, 100);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No eligible earnings', $result['message']);
    }

    public function test_amount_exceeds_eligible_balance_returns_failure(): void
    {
        $owner = User::factory()->create(['payout_method' => 'maya', 'payout_details' => '09171234567', 'kyc_verified_at' => now()]);
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $eligibility = Mockery::mock(CalculateEligibility::class);
        $eligibility->shouldReceive('forOwner')->with($community)->andReturn([50.0, 100.0, null]);

        $action = new RequestOwnerPayout($eligibility, app(\App\Services\Wallet\WalletService::class));
        $result = $action->execute($owner, $community, 100);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('exceeds eligible balance', $result['message']);
    }

    public function test_successful_payout_request_creates_record(): void
    {
        $owner = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567', 'kyc_verified_at' => now()]);
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $eligibility = Mockery::mock(CalculateEligibility::class);
        $eligibility->shouldReceive('forOwner')->with($community)->andReturn([200.0, 50.0, null]);

        $action = new RequestOwnerPayout($eligibility, app(\App\Services\Wallet\WalletService::class));
        $result = $action->execute($owner, $community, 150);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('submitted', $result['message']);
        $this->assertDatabaseHas('payout_requests', [
            'user_id' => $owner->id,
            'type' => PayoutRequest::TYPE_OWNER,
            'community_id' => $community->id,
            'amount' => 150,
            'status' => PayoutRequest::STATUS_PENDING,
        ]);
    }
}
