<?php

namespace Tests\Feature\Actions\Admin;

use App\Actions\Admin\ApprovePayoutRequest;
use App\Models\Affiliate;
use App\Models\Community;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovePayoutRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_approval_uses_user_level_payout_method_when_affiliate_row_is_empty(): void
    {
        $this->mock(XenditService::class, function ($mock) {
            $mock->shouldReceive('createPayout')
                ->once()
                ->withArgs(function ($payload) {
                    return $payload['channel_code'] === 'PH_GCASH'
                        && $payload['channel_properties']['account_number'] === '09955300226'
                        && (float) $payload['amount'] === 99.50;
                })
                ->andReturn(['id' => 'po_test_123', 'status' => 'ACCEPTED']);
        });

        $user = User::factory()->create([
            'payout_method' => 'gcash',
            'payout_details' => '09955300226',
            'kyc_verified_at' => now(),
        ]);
        $community = Community::factory()->create();
        $affiliate = Affiliate::create([
            'user_id' => $user->id,
            'community_id' => $community->id,
            'code' => 'AFF-APPROVE-'.uniqid(),
            'status' => Affiliate::STATUS_ACTIVE,
            'payout_method' => null,
            'payout_details' => null,
            'total_earned' => 99.50,
            'total_paid' => 0,
        ]);
        $payoutRequest = PayoutRequest::create([
            'user_id' => $user->id,
            'type' => PayoutRequest::TYPE_AFFILIATE,
            'community_id' => $community->id,
            'affiliate_id' => $affiliate->id,
            'amount' => 99.50,
            'eligible_amount' => 99.50,
            'status' => PayoutRequest::STATUS_PENDING,
        ]);

        app(ApprovePayoutRequest::class)->execute($payoutRequest->fresh());

        $this->assertDatabaseHas('payout_requests', [
            'id' => $payoutRequest->id,
            'status' => PayoutRequest::STATUS_APPROVED,
            'xendit_reference' => 'po_test_123',
        ]);
    }
}
