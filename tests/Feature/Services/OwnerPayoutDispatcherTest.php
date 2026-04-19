<?php

namespace Tests\Feature\Services;

use App\Models\Community;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payout\OwnerEarningsCalculator;
use App\Services\Payout\OwnerPayoutDispatcher;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class OwnerPayoutDispatcherTest extends TestCase
{
    use RefreshDatabase;

    private function makeDispatcher(?XenditService $xendit = null): OwnerPayoutDispatcher
    {
        return new OwnerPayoutDispatcher(
            new OwnerEarningsCalculator,
            $xendit ?? Mockery::mock(XenditService::class),
        );
    }

    private function communityWithPendingEarnings(string $payoutMethod = 'gcash', string $payoutDetails = '09171234567'): Community
    {
        $owner = User::factory()->create(['payout_method' => $payoutMethod, 'payout_details' => $payoutDetails]);
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();
        $sub = Subscription::factory()->active()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        Payment::create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $member->id,
            'amount' => 1000,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'metadata' => [],
            'paid_at' => now(),
        ]);

        return $community;
    }

    // ── canDispatch ───────────────────────────────────────────────────────────

    public function test_can_dispatch_when_owner_has_gcash(): void
    {
        $community = $this->communityWithPendingEarnings('gcash', '09171234567');
        $dispatcher = $this->makeDispatcher();

        $this->assertTrue($dispatcher->canDispatch($community));
    }

    public function test_can_dispatch_when_owner_has_maya(): void
    {
        $community = $this->communityWithPendingEarnings('maya', '09179999999');
        $dispatcher = $this->makeDispatcher();

        $this->assertTrue($dispatcher->canDispatch($community));
    }

    public function test_cannot_dispatch_when_payout_method_is_null(): void
    {
        $owner = User::factory()->create(['payout_method' => null, 'payout_details' => null]);
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $dispatcher = $this->makeDispatcher();

        $this->assertFalse($dispatcher->canDispatch($community));
    }

    public function test_cannot_dispatch_when_payout_details_is_null(): void
    {
        $owner = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => null]);
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $dispatcher = $this->makeDispatcher();

        $this->assertFalse($dispatcher->canDispatch($community));
    }

    public function test_cannot_dispatch_with_unknown_payout_method(): void
    {
        $owner = User::factory()->create(['payout_method' => 'bank', 'payout_details' => '123456']);
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $dispatcher = $this->makeDispatcher();

        $this->assertFalse($dispatcher->canDispatch($community));
    }

    // ── dispatch ──────────────────────────────────────────────────────────────

    public function test_dispatch_calls_xendit_and_records_payout(): void
    {
        $community = $this->communityWithPendingEarnings();

        $xendit = Mockery::mock(XenditService::class);
        $xendit->shouldReceive('createPayout')
            ->once()
            ->withArgs(fn ($args) => $args['currency'] === 'PHP'
                && $args['channel_code'] === 'PH_GCASH'
                && $args['amount'] > 0
            )
            ->andReturn(['id' => 'xnd_payout_123']);

        $dispatcher = $this->makeDispatcher($xendit);
        $result = $dispatcher->dispatch($community);

        $this->assertSame('xnd_payout_123', $result['reference']);
        $this->assertGreaterThan(0, $result['amount']);

        $this->assertDatabaseHas('owner_payouts', [
            'community_id' => $community->id,
            'xendit_reference' => 'xnd_payout_123',
            'status' => 'accepted',
        ]);
    }

    public function test_dispatch_uses_ph_paymaya_for_maya_method(): void
    {
        $community = $this->communityWithPendingEarnings('maya', '09179999999');

        $xendit = Mockery::mock(XenditService::class);
        $xendit->shouldReceive('createPayout')
            ->once()
            ->withArgs(fn ($args) => $args['channel_code'] === 'PH_PAYMAYA')
            ->andReturn(['id' => 'xnd_payout_456']);

        $dispatcher = $this->makeDispatcher($xendit);
        $dispatcher->dispatch($community);
    }

    public function test_dispatch_throws_when_no_pending_amount(): void
    {
        $owner = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567']);
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $xendit = Mockery::mock(XenditService::class);
        $xendit->shouldNotReceive('createPayout');

        $dispatcher = $this->makeDispatcher($xendit);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No pending amount for this community.');

        $dispatcher->dispatch($community);
    }

    public function test_dispatch_falls_back_to_reference_id_when_xendit_returns_no_id(): void
    {
        $community = $this->communityWithPendingEarnings();

        $xendit = Mockery::mock(XenditService::class);
        $xendit->shouldReceive('createPayout')->andReturn([]); // no 'id' key

        $dispatcher = $this->makeDispatcher($xendit);
        $result = $dispatcher->dispatch($community);

        $this->assertStringStartsWith('owner-'.$community->id.'-', $result['reference']);
    }

    public function test_dispatch_propagates_xendit_exceptions(): void
    {
        $community = $this->communityWithPendingEarnings();

        $xendit = Mockery::mock(XenditService::class);
        $xendit->shouldReceive('createPayout')->andThrow(new \RuntimeException('Xendit API down'));

        $dispatcher = $this->makeDispatcher($xendit);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Xendit API down');

        $dispatcher->dispatch($community);
    }

    // ── batchDispatch ─────────────────────────────────────────────────────────

    public function test_batch_pays_all_eligible_communities(): void
    {
        $c1 = $this->communityWithPendingEarnings();
        $c2 = $this->communityWithPendingEarnings();

        $xendit = Mockery::mock(XenditService::class);
        $xendit->shouldReceive('createPayout')->twice()->andReturn(['id' => 'xnd_batch']);

        $dispatcher = $this->makeDispatcher($xendit);
        $result = $dispatcher->batchDispatch(collect([$c1, $c2]));

        $this->assertSame(2, $result['paid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_batch_skips_communities_without_payout_details(): void
    {
        $good = $this->communityWithPendingEarnings();
        $bad = Community::factory()->create([
            'owner_id' => User::factory()->create(['payout_method' => null])->id,
        ]);

        $xendit = Mockery::mock(XenditService::class);
        $xendit->shouldReceive('createPayout')->once()->andReturn(['id' => 'xnd_ok']);

        $dispatcher = $this->makeDispatcher($xendit);
        $result = $dispatcher->batchDispatch(collect([$good, $bad]));

        $this->assertSame(1, $result['paid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_batch_silently_skips_communities_with_no_pending_amount(): void
    {
        $no_earnings = Community::factory()->create([
            'owner_id' => User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567'])->id,
        ]);
        $with_earnings = $this->communityWithPendingEarnings();

        $xendit = Mockery::mock(XenditService::class);
        $xendit->shouldReceive('createPayout')->once()->andReturn(['id' => 'xnd_ok']);

        $dispatcher = $this->makeDispatcher($xendit);
        $result = $dispatcher->batchDispatch(collect([$no_earnings, $with_earnings]));

        $this->assertSame(1, $result['paid']);
        $this->assertEmpty($result['errors']); // "no pending" is not an error
    }

    public function test_batch_collects_xendit_errors_without_stopping(): void
    {
        $c1 = $this->communityWithPendingEarnings();
        $c2 = $this->communityWithPendingEarnings();

        $xendit = Mockery::mock(XenditService::class);
        $xendit->shouldReceive('createPayout')
            ->twice()
            ->andThrow(new \RuntimeException('Channel unavailable'));

        $dispatcher = $this->makeDispatcher($xendit);
        $result = $dispatcher->batchDispatch(collect([$c1, $c2]));

        $this->assertSame(0, $result['paid']);
        $this->assertCount(2, $result['errors']);
        $this->assertStringContainsString('Channel unavailable', $result['message']);
    }

    public function test_batch_message_includes_custom_label(): void
    {
        $xendit = Mockery::mock(XenditService::class);

        $dispatcher = $this->makeDispatcher($xendit);
        $result = $dispatcher->batchDispatch(collect([]), 'selected owners');

        $this->assertStringContainsString('selected owners', $result['message']);
    }
}
