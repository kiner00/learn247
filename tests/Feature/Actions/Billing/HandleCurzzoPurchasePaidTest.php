<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Affiliate\RecordAffiliateConversion;
use App\Actions\Billing\SendChaChing;
use App\Actions\Billing\WebhookHandlers\HandleCurzzoPurchasePaid;
use App\Models\Affiliate;
use App\Models\Community;
use App\Models\Curzzo;
use App\Models\CurzzoPurchase;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class HandleCurzzoPurchasePaidTest extends TestCase
{
    use RefreshDatabase;

    private function createPendingPurchase(array $overrides = []): array
    {
        $owner     = User::factory()->kycVerified()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo    = Curzzo::create([
            'community_id'  => $community->id,
            'name'          => 'Test Bot',
            'description'   => 'A test curzzo',
            'instructions'  => 'Be helpful',
            'price'         => 99.00,
            'currency'      => 'PHP',
            'billing_type'  => $overrides['billing_type'] ?? 'one_time',
            'is_active'     => true,
            'position'      => 1,
        ]);

        $buyer    = User::factory()->create();
        $purchase = CurzzoPurchase::create([
            'user_id'    => $buyer->id,
            'curzzo_id'  => $curzzo->id,
            'xendit_id'  => $overrides['xendit_id'] ?? 'inv_purchase_123',
            'status'     => CurzzoPurchase::STATUS_PENDING,
            'paid_at'    => null,
            'expires_at' => $overrides['expires_at'] ?? null,
        ]);

        return compact('owner', 'community', 'curzzo', 'buyer', 'purchase');
    }

    // ── matches() ───────────────────────────────────────────────────────────

    public function test_matches_returns_true_for_existing_purchase(): void
    {
        $data    = $this->createPendingPurchase();
        $handler = app(HandleCurzzoPurchasePaid::class);

        $this->assertTrue($handler->matches('inv_purchase_123'));
    }

    public function test_matches_returns_false_for_nonexistent_xendit_id(): void
    {
        $handler = app(HandleCurzzoPurchasePaid::class);

        $this->assertFalse($handler->matches('inv_does_not_exist'));
    }

    // ── handle() with PAID status ───────────────────────────────────────────

    public function test_marks_purchase_as_paid(): void
    {
        $data    = $this->createPendingPurchase();
        $handler = app(HandleCurzzoPurchasePaid::class);
        $handler->matches('inv_purchase_123');

        $handler->handle([], 'evt_1', 'PAID');

        $data['purchase']->refresh();
        $this->assertEquals(CurzzoPurchase::STATUS_PAID, $data['purchase']->status);
        $this->assertNotNull($data['purchase']->paid_at);
    }

    public function test_one_time_purchase_has_null_expires_at(): void
    {
        $data    = $this->createPendingPurchase(['billing_type' => 'one_time']);
        $handler = app(HandleCurzzoPurchasePaid::class);
        $handler->matches('inv_purchase_123');

        $handler->handle([], 'evt_1', 'PAID');

        $data['purchase']->refresh();
        $this->assertNull($data['purchase']->expires_at);
    }

    public function test_monthly_purchase_sets_expires_at(): void
    {
        $data    = $this->createPendingPurchase(['billing_type' => 'monthly']);
        $handler = app(HandleCurzzoPurchasePaid::class);
        $handler->matches('inv_purchase_123');

        $handler->handle([], 'evt_1', 'PAID');

        $data['purchase']->refresh();
        $this->assertNotNull($data['purchase']->expires_at);
        // Should be about 1 month from now
        $this->assertTrue($data['purchase']->expires_at->isAfter(now()->addDays(27)));
    }

    public function test_monthly_renewal_extends_from_existing_future_expiry(): void
    {
        $futureDate = now()->addDays(10);
        $data       = $this->createPendingPurchase([
            'billing_type' => 'monthly',
            'expires_at'   => $futureDate,
        ]);

        $handler = app(HandleCurzzoPurchasePaid::class);
        $handler->matches('inv_purchase_123');

        $handler->handle([], 'evt_1', 'SETTLED');

        $data['purchase']->refresh();
        // Should extend from the existing future date, not from now
        $this->assertTrue($data['purchase']->expires_at->isAfter($futureDate->addDays(27)));
    }

    // ── Non-PAID statuses ───────────────────────────────────────────────────

    public function test_ignores_expired_status(): void
    {
        $data    = $this->createPendingPurchase();
        $handler = app(HandleCurzzoPurchasePaid::class);
        $handler->matches('inv_purchase_123');

        $handler->handle([], 'evt_1', 'EXPIRED');

        $data['purchase']->refresh();
        $this->assertEquals(CurzzoPurchase::STATUS_PENDING, $data['purchase']->status);
    }

    public function test_ignores_failed_status(): void
    {
        $data    = $this->createPendingPurchase();
        $handler = app(HandleCurzzoPurchasePaid::class);
        $handler->matches('inv_purchase_123');

        $handler->handle([], 'evt_1', 'FAILED');

        $data['purchase']->refresh();
        $this->assertEquals(CurzzoPurchase::STATUS_PENDING, $data['purchase']->status);
    }

    public function test_ignores_unknown_status(): void
    {
        $data    = $this->createPendingPurchase();
        $handler = app(HandleCurzzoPurchasePaid::class);
        $handler->matches('inv_purchase_123');

        $handler->handle([], 'evt_1', 'PENDING');

        $data['purchase']->refresh();
        $this->assertEquals(CurzzoPurchase::STATUS_PENDING, $data['purchase']->status);
    }

    // ── Affiliate conversion ────────────────────────────────────────────────

    public function test_records_affiliate_conversion_and_sends_cha_ching(): void
    {
        $data          = $this->createPendingPurchase();
        $affiliateUser = User::factory()->create();
        $affiliate     = Affiliate::create([
            'community_id' => $data['community']->id,
            'user_id'      => $affiliateUser->id,
            'code'         => 'AFF123',
            'status'       => Affiliate::STATUS_ACTIVE,
            'total_earned' => 0,
            'total_paid'   => 0,
        ]);
        $data['purchase']->update(['affiliate_id' => $affiliate->id]);

        $mockRecordConversion = $this->mock(RecordAffiliateConversion::class);
        $mockRecordConversion->shouldReceive('executeForCurzzo')
            ->once()
            ->andReturn([
                'sale_amount' => 99.00,
                'commission'  => 9.90,
            ]);

        $mockChaChing = $this->mock(SendChaChing::class);
        $mockChaChing->shouldReceive('execute')
            ->once()
            ->withArgs(function ($affiliateUser, $creator, $community, $saleAmount, $commission) use ($data) {
                return $saleAmount === 99.00
                    && $commission === 9.90
                    && $community->id === $data['community']->id;
            });

        $handler = app(HandleCurzzoPurchasePaid::class);
        $handler->matches('inv_purchase_123');
        $handler->handle([], 'evt_1', 'PAID');
    }

    public function test_skips_cha_ching_when_no_affiliate_conversion(): void
    {
        $data = $this->createPendingPurchase();

        $mockRecordConversion = $this->mock(RecordAffiliateConversion::class);
        $mockRecordConversion->shouldReceive('executeForCurzzo')
            ->once()
            ->andReturn(null);

        $mockChaChing = $this->mock(SendChaChing::class);
        $mockChaChing->shouldNotReceive('execute');

        $handler = app(HandleCurzzoPurchasePaid::class);
        $handler->matches('inv_purchase_123');
        $handler->handle([], 'evt_1', 'PAID');
    }

    // ── Error handling ──────────────────────────────────────────────────────

    public function test_logs_error_and_rethrows_on_exception(): void
    {
        $data = $this->createPendingPurchase();

        $mockRecordConversion = $this->mock(RecordAffiliateConversion::class);
        $mockRecordConversion->shouldReceive('executeForCurzzo')
            ->once()
            ->andThrow(new \RuntimeException('DB connection lost'));

        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'HandleCurzzoPurchasePaid failed'));

        $handler = app(HandleCurzzoPurchasePaid::class);
        $handler->matches('inv_purchase_123');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB connection lost');

        $handler->handle([], 'evt_1', 'PAID');
    }
}
