<?php

namespace Tests\Feature\Console;

use App\Actions\Affiliate\MarkAffiliateConversionPaid;
use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SyncPayoutStatusesTest extends TestCase
{
    use RefreshDatabase;

    private function mockXendit(array $responses = []): XenditService
    {
        $mock = Mockery::mock(XenditService::class);

        foreach ($responses as $reference => $response) {
            if ($response instanceof \Exception) {
                $mock->shouldReceive('getPayout')
                    ->with($reference)
                    ->andThrow($response);
            } else {
                $mock->shouldReceive('getPayout')
                    ->with($reference)
                    ->andReturn($response);
            }
        }

        $this->app->instance(XenditService::class, $mock);

        return $mock;
    }

    private function createApprovedPayoutRequest(
        string $type = PayoutRequest::TYPE_OWNER,
        float $amount = 100.00,
        string $xenditRef = 'po_test_123',
        ?int $affiliateId = null,
    ): PayoutRequest {
        $user = User::factory()->create();

        return PayoutRequest::create([
            'user_id'           => $user->id,
            'type'              => $type,
            'community_id'      => Community::factory()->create(['owner_id' => $user->id])->id,
            'affiliate_id'      => $affiliateId,
            'amount'            => $amount,
            'eligible_amount'   => $amount,
            'status'            => PayoutRequest::STATUS_APPROVED,
            'xendit_reference'  => $xenditRef,
        ]);
    }

    private function createAffiliateWithConversions(
        int $count = 2,
        float $commission = 50.00,
    ): array {
        $user = User::factory()->create();
        $community = Community::factory()->create();

        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'code'          => strtoupper(fake()->unique()->lexify('????????')),
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        $conversions = [];
        for ($i = 0; $i < $count; $i++) {
            $sub = Subscription::factory()->create([
                'community_id' => $community->id,
                'status'       => Subscription::STATUS_ACTIVE,
            ]);

            $conversions[] = AffiliateConversion::create([
                'affiliate_id'      => $affiliate->id,
                'subscription_id'   => $sub->id,
                'referred_user_id'  => $sub->user_id,
                'sale_amount'       => 500,
                'platform_fee'      => 75,
                'commission_amount' => $commission,
                'creator_amount'    => 500 - 75 - $commission,
                'status'            => AffiliateConversion::STATUS_PENDING,
            ]);
        }

        return [$affiliate, $conversions, $community, $user];
    }

    // ─── no approved requests ──────────────────────────────────────────────────

    public function test_command_succeeds_with_no_approved_requests(): void
    {
        $this->mockXendit();

        $this->artisan('payouts:sync')
            ->expectsOutputToContain('No approved payout requests to sync')
            ->assertExitCode(0);
    }

    // ─── owner payout succeeded ────────────────────────────────────────────────

    public function test_owner_payout_marked_paid_on_xendit_succeeded(): void
    {
        $request = $this->createApprovedPayoutRequest(
            type: PayoutRequest::TYPE_OWNER,
            xenditRef: 'po_owner_1',
        );

        $this->mockXendit([
            'po_owner_1' => ['status' => 'SUCCEEDED'],
        ]);

        $this->artisan('payouts:sync')->assertExitCode(0);

        $request->refresh();
        $this->assertEquals(PayoutRequest::STATUS_PAID, $request->status);
    }

    public function test_owner_payout_marked_paid_on_xendit_completed(): void
    {
        $request = $this->createApprovedPayoutRequest(
            type: PayoutRequest::TYPE_OWNER,
            xenditRef: 'po_owner_2',
        );

        $this->mockXendit([
            'po_owner_2' => ['status' => 'COMPLETED'],
        ]);

        $this->artisan('payouts:sync')->assertExitCode(0);

        $request->refresh();
        $this->assertEquals(PayoutRequest::STATUS_PAID, $request->status);
    }

    // ─── affiliate payout succeeded → marks conversions paid ───────────────────

    public function test_affiliate_payout_marks_conversions_paid(): void
    {
        [$affiliate, $conversions] = $this->createAffiliateWithConversions(2, 50.00);

        $request = $this->createApprovedPayoutRequest(
            type: PayoutRequest::TYPE_AFFILIATE,
            amount: 100.00,
            xenditRef: 'po_aff_1',
            affiliateId: $affiliate->id,
        );

        $this->mockXendit([
            'po_aff_1' => ['status' => 'SUCCEEDED'],
        ]);

        $this->artisan('payouts:sync')->assertExitCode(0);

        $request->refresh();
        $this->assertEquals(PayoutRequest::STATUS_PAID, $request->status);

        foreach ($conversions as $conv) {
            $conv->refresh();
            $this->assertEquals(AffiliateConversion::STATUS_PAID, $conv->status);
        }
    }

    // ─── failed/cancelled/reversed → reverted to pending ───────────────────────

    public function test_failed_payout_reverts_to_pending(): void
    {
        $request = $this->createApprovedPayoutRequest(xenditRef: 'po_fail_1');

        $this->mockXendit([
            'po_fail_1' => ['status' => 'FAILED'],
        ]);

        $this->artisan('payouts:sync')->assertExitCode(0);

        $request->refresh();
        $this->assertEquals(PayoutRequest::STATUS_PENDING, $request->status);
    }

    public function test_cancelled_payout_reverts_to_pending(): void
    {
        $request = $this->createApprovedPayoutRequest(xenditRef: 'po_cancel_1');

        $this->mockXendit([
            'po_cancel_1' => ['status' => 'CANCELLED'],
        ]);

        $this->artisan('payouts:sync')->assertExitCode(0);

        $request->refresh();
        $this->assertEquals(PayoutRequest::STATUS_PENDING, $request->status);
    }

    public function test_reversed_payout_reverts_to_pending(): void
    {
        $request = $this->createApprovedPayoutRequest(xenditRef: 'po_reverse_1');

        $this->mockXendit([
            'po_reverse_1' => ['status' => 'REVERSED'],
        ]);

        $this->artisan('payouts:sync')->assertExitCode(0);

        $request->refresh();
        $this->assertEquals(PayoutRequest::STATUS_PENDING, $request->status);
    }

    // ─── still processing → no change ──────────────────────────────────────────

    public function test_processing_payout_is_skipped(): void
    {
        $request = $this->createApprovedPayoutRequest(xenditRef: 'po_proc_1');

        $this->mockXendit([
            'po_proc_1' => ['status' => 'ACCEPTED'],
        ]);

        $this->artisan('payouts:sync')->assertExitCode(0);

        $request->refresh();
        $this->assertEquals(PayoutRequest::STATUS_APPROVED, $request->status);
    }

    // ─── exception handling ────────────────────────────────────────────────────

    public function test_exception_is_caught_and_command_continues(): void
    {
        $request1 = $this->createApprovedPayoutRequest(xenditRef: 'po_err_1');
        $request2 = $this->createApprovedPayoutRequest(xenditRef: 'po_ok_1');

        $this->mockXendit([
            'po_err_1' => new \RuntimeException('Network error'),
            'po_ok_1'  => ['status' => 'SUCCEEDED'],
        ]);

        $this->artisan('payouts:sync')->assertExitCode(0);

        // First request unchanged due to error
        $request1->refresh();
        $this->assertEquals(PayoutRequest::STATUS_APPROVED, $request1->status);

        // Second request still processed
        $request2->refresh();
        $this->assertEquals(PayoutRequest::STATUS_PAID, $request2->status);
    }

    // ─── ignores requests without xendit_reference ─────────────────────────────

    public function test_ignores_requests_without_xendit_reference(): void
    {
        $user = User::factory()->create();
        PayoutRequest::create([
            'user_id'           => $user->id,
            'type'              => PayoutRequest::TYPE_OWNER,
            'community_id'      => Community::factory()->create(['owner_id' => $user->id])->id,
            'amount'            => 100,
            'eligible_amount'   => 100,
            'status'            => PayoutRequest::STATUS_APPROVED,
            'xendit_reference'  => null,
        ]);

        $this->mockXendit();

        $this->artisan('payouts:sync')
            ->expectsOutputToContain('No approved payout requests to sync')
            ->assertExitCode(0);
    }

    // ─── only processes approved requests ──────────────────────────────────────

    public function test_only_processes_approved_requests(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);

        // Pending - should be ignored
        PayoutRequest::create([
            'user_id'          => $user->id,
            'type'             => PayoutRequest::TYPE_OWNER,
            'community_id'     => $community->id,
            'amount'           => 100,
            'eligible_amount'  => 100,
            'status'           => PayoutRequest::STATUS_PENDING,
            'xendit_reference' => 'po_pending_1',
        ]);

        $this->mockXendit();

        $this->artisan('payouts:sync')
            ->expectsOutputToContain('No approved payout requests to sync')
            ->assertExitCode(0);
    }
}
