<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\WebhookHandlers\HandleCreatorPlanPaid;
use App\Models\CreatorSubscription;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class HandleCreatorPlanPaidTest extends TestCase
{
    use RefreshDatabase;

    private function makeCreatorSub(array $overrides = []): CreatorSubscription
    {
        $user = User::factory()->create();

        return CreatorSubscription::create(array_merge([
            'user_id' => $user->id,
            'plan' => CreatorSubscription::PLAN_BASIC,
            'status' => CreatorSubscription::STATUS_PENDING,
            'xendit_id' => 'inv_cs_'.uniqid(),
            'expires_at' => null,
        ], $overrides));
    }

    // ─── matches() ─────────────────────────────────────────────────────────────

    public function test_matches_returns_true_when_creator_subscription_exists(): void
    {
        $cs = $this->makeCreatorSub(['xendit_id' => 'inv_cs_match']);
        $handler = app(HandleCreatorPlanPaid::class);

        $this->assertTrue($handler->matches('inv_cs_match'));
    }

    public function test_matches_returns_false_when_no_creator_subscription(): void
    {
        $handler = app(HandleCreatorPlanPaid::class);

        $this->assertFalse($handler->matches('inv_nonexistent'));
    }

    // ─── handle() — PAID status ────────────────────────────────────────────────

    public function test_paid_status_activates_subscription_and_sets_expiry(): void
    {
        $cs = $this->makeCreatorSub(['xendit_id' => 'inv_cs_paid']);
        $handler = app(HandleCreatorPlanPaid::class);
        $handler->matches('inv_cs_paid');

        $handler->handle([
            'amount' => 500,
            'payment_channel' => 'GCASH',
            'currency' => 'PHP',
            'payment_id' => 'pay_123',
        ], 'evt_cs_paid', 'PAID');

        $cs->refresh();
        $this->assertEquals(CreatorSubscription::STATUS_ACTIVE, $cs->status);
        $this->assertNotNull($cs->expires_at);
        $this->assertTrue($cs->expires_at->isFuture());

        // Payment record should be created
        $this->assertDatabaseHas('payments', [
            'user_id' => $cs->user_id,
            'status' => Payment::STATUS_PAID,
            'amount' => 500,
            'xendit_event_id' => 'evt_cs_paid',
        ]);
    }

    public function test_settled_status_activates_subscription(): void
    {
        $cs = $this->makeCreatorSub(['xendit_id' => 'inv_cs_settled']);
        $handler = app(HandleCreatorPlanPaid::class);
        $handler->matches('inv_cs_settled');

        $handler->handle([
            'amount' => 500,
            'payment_channel' => 'GCASH',
            'currency' => 'PHP',
            'external_id' => 'ext_123',
        ], 'evt_cs_settled', 'SETTLED');

        $cs->refresh();
        $this->assertEquals(CreatorSubscription::STATUS_ACTIVE, $cs->status);
        $this->assertNotNull($cs->expires_at);

        // Should use external_id as fallback provider_reference
        $this->assertDatabaseHas('payments', [
            'xendit_event_id' => 'evt_cs_settled',
            'provider_reference' => 'ext_123',
        ]);
    }

    public function test_paid_extends_from_existing_future_expiry(): void
    {
        $futureExpiry = now()->addDays(15);
        $cs = $this->makeCreatorSub([
            'xendit_id' => 'inv_cs_renew',
            'status' => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => $futureExpiry,
        ]);

        $handler = app(HandleCreatorPlanPaid::class);
        $handler->matches('inv_cs_renew');

        $handler->handle([
            'amount' => 500,
            'payment_channel' => 'GCASH',
            'currency' => 'PHP',
            'payment_id' => 'pay_renew',
        ], 'evt_cs_renew', 'PAID');

        $cs->refresh();
        $expectedExpiry = $futureExpiry->copy()->addMonth();
        $this->assertTrue(
            $cs->expires_at->diffInSeconds($expectedExpiry) < 5,
            'Expiry should extend from the existing future expiry by one month'
        );
    }

    public function test_paid_sets_expiry_from_now_when_expired(): void
    {
        $cs = $this->makeCreatorSub([
            'xendit_id' => 'inv_cs_expired_renew',
            'status' => CreatorSubscription::STATUS_EXPIRED,
            'expires_at' => now()->subDays(5),
        ]);

        $handler = app(HandleCreatorPlanPaid::class);
        $handler->matches('inv_cs_expired_renew');

        $handler->handle([
            'amount' => 500,
            'payment_channel' => 'GCASH',
            'currency' => 'PHP',
            'payment_id' => 'pay_exp_renew',
        ], 'evt_cs_exp_renew', 'PAID');

        $cs->refresh();
        $this->assertEquals(CreatorSubscription::STATUS_ACTIVE, $cs->status);
        // Should set from now() since existing expires_at is in the past
        $expectedExpiry = now()->addMonth();
        $this->assertTrue(
            $cs->expires_at->diffInSeconds($expectedExpiry) < 5,
            'Expiry should be set from now when previous expiry is in the past'
        );
    }

    // ─── handle() — non-PAID statuses ──────────────────────────────────────────

    public function test_expired_status_marks_subscription_expired(): void
    {
        $cs = $this->makeCreatorSub(['xendit_id' => 'inv_cs_exp']);
        $handler = app(HandleCreatorPlanPaid::class);
        $handler->matches('inv_cs_exp');

        $handler->handle([], 'evt_cs_exp', 'EXPIRED');

        $cs->refresh();
        $this->assertEquals(CreatorSubscription::STATUS_EXPIRED, $cs->status);

        // No payment record should be created for non-active statuses
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_failed_status_marks_subscription_cancelled(): void
    {
        $cs = $this->makeCreatorSub(['xendit_id' => 'inv_cs_fail']);
        $handler = app(HandleCreatorPlanPaid::class);
        $handler->matches('inv_cs_fail');

        $handler->handle([], 'evt_cs_fail', 'FAILED');

        $cs->refresh();
        $this->assertEquals(CreatorSubscription::STATUS_CANCELLED, $cs->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_unknown_status_marks_subscription_pending(): void
    {
        $cs = $this->makeCreatorSub([
            'xendit_id' => 'inv_cs_unk',
            'status' => CreatorSubscription::STATUS_ACTIVE,
        ]);

        $handler = app(HandleCreatorPlanPaid::class);
        $handler->matches('inv_cs_unk');

        $handler->handle([], 'evt_cs_unk', 'SOME_UNKNOWN');

        $cs->refresh();
        $this->assertEquals(CreatorSubscription::STATUS_PENDING, $cs->status);
        $this->assertDatabaseCount('payments', 0);
    }

    // ─── handle() — processing fee calculation ─────────────────────────────────

    public function test_processing_fee_calculated_from_payment_channel(): void
    {
        $cs = $this->makeCreatorSub(['xendit_id' => 'inv_cs_fee']);
        $handler = app(HandleCreatorPlanPaid::class);
        $handler->matches('inv_cs_fee');

        $handler->handle([
            'amount' => 1000,
            'payment_channel' => 'GCASH',
            'currency' => 'PHP',
            'payment_id' => 'pay_fee',
        ], 'evt_cs_fee', 'PAID');

        // GCASH fee = 1000 * 0.023 = 23.00
        $this->assertDatabaseHas('payments', [
            'xendit_event_id' => 'evt_cs_fee',
            'processing_fee' => 23.00,
            'platform_fee' => 0,
        ]);
    }

    public function test_annual_paid_extends_by_one_year(): void
    {
        $cs = $this->makeCreatorSub([
            'xendit_id' => 'inv_cs_annual',
            'plan' => CreatorSubscription::PLAN_PRO,
            'billing_cycle' => CreatorSubscription::CYCLE_ANNUAL,
            'expires_at' => null,
        ]);
        $handler = app(HandleCreatorPlanPaid::class);
        $handler->matches('inv_cs_annual');

        $handler->handle([
            'amount' => 19990,
            'payment_channel' => 'GCASH',
            'currency' => 'PHP',
            'payment_id' => 'pay_annual',
        ], 'evt_cs_annual', 'PAID');

        $cs->refresh();
        $this->assertEquals(CreatorSubscription::STATUS_ACTIVE, $cs->status);
        $expectedExpiry = now()->addYear();
        $this->assertTrue(
            $cs->expires_at->diffInSeconds($expectedExpiry) < 5,
            'Annual cycle should extend expiry by one year'
        );
    }

    public function test_annual_paid_extends_from_existing_future_expiry_by_one_year(): void
    {
        $futureExpiry = now()->addDays(15);
        $cs = $this->makeCreatorSub([
            'xendit_id' => 'inv_cs_annual_renew',
            'billing_cycle' => CreatorSubscription::CYCLE_ANNUAL,
            'status' => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => $futureExpiry,
        ]);

        $handler = app(HandleCreatorPlanPaid::class);
        $handler->matches('inv_cs_annual_renew');

        $handler->handle([
            'amount' => 19990,
            'payment_channel' => 'GCASH',
            'currency' => 'PHP',
            'payment_id' => 'pay_annual_renew',
        ], 'evt_cs_annual_renew', 'PAID');

        $cs->refresh();
        $expectedExpiry = $futureExpiry->copy()->addYear();
        $this->assertTrue(
            $cs->expires_at->diffInSeconds($expectedExpiry) < 5,
            'Annual renewal should extend expiry by one year from current future expiry'
        );
    }

    public function test_paid_with_null_expires_at_sets_from_now(): void
    {
        $cs = $this->makeCreatorSub(['xendit_id' => 'inv_cs_null_exp', 'expires_at' => null]);
        $handler = app(HandleCreatorPlanPaid::class);
        $handler->matches('inv_cs_null_exp');

        $handler->handle([
            'amount' => 500,
            'payment_channel' => 'GCASH',
            'currency' => 'PHP',
            'payment_id' => 'pay_null_exp',
        ], 'evt_cs_null_exp', 'PAID');

        $cs->refresh();
        $this->assertEquals(CreatorSubscription::STATUS_ACTIVE, $cs->status);
        $expectedExpiry = now()->addMonth();
        $this->assertTrue(
            $cs->expires_at->diffInSeconds($expectedExpiry) < 5,
            'Expiry should be set from now when previous expires_at is null'
        );
    }

    public function test_handle_logs_error_and_rethrows_on_exception(): void
    {
        $cs = $this->makeCreatorSub(['xendit_id' => 'inv_cs_err']);
        $handler = app(HandleCreatorPlanPaid::class);
        $handler->matches('inv_cs_err');

        // Force an exception inside the try block by making the model update throw
        CreatorSubscription::updating(function () {
            throw new \RuntimeException('forced update failure');
        });

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn ($msg, $ctx) => $msg === 'HandleCreatorPlanPaid failed'
                && $ctx['user_id'] === $cs->user_id
                && str_contains($ctx['error'], 'forced update failure'));
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('forced update failure');

        $handler->handle([
            'amount' => 500,
            'payment_channel' => 'GCASH',
            'currency' => 'PHP',
        ], 'evt_cs_err', 'PAID');
    }
}
