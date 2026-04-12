<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\WebhookHandlers\HandleCurzzoTopupPaid;
use App\Models\Community;
use App\Models\CurzzoTopup;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class HandleCurzzoTopupPaidTest extends TestCase
{
    use RefreshDatabase;

    private function createPendingTopup(array $overrides = []): array
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();

        $topup = CurzzoTopup::create([
            'user_id'      => $user->id,
            'community_id' => $community->id,
            'xendit_id'    => $overrides['xendit_id'] ?? 'inv_topup_123',
            'status'       => CurzzoTopup::STATUS_PENDING,
            'messages'     => $overrides['messages'] ?? 100,
            'messages_used' => 0,
            'paid_at'      => null,
            'expires_at'   => null,
        ]);

        return compact('community', 'user', 'topup');
    }

    // ── matches() ───────────────────────────────────────────────────────────

    public function test_matches_returns_true_for_existing_topup(): void
    {
        $data    = $this->createPendingTopup();
        $handler = new HandleCurzzoTopupPaid();

        $this->assertTrue($handler->matches('inv_topup_123'));
    }

    public function test_matches_returns_false_for_nonexistent_xendit_id(): void
    {
        $handler = new HandleCurzzoTopupPaid();

        $this->assertFalse($handler->matches('inv_does_not_exist'));
    }

    // ── handle() with PAID status — message pack ────────────────────────────

    public function test_marks_message_pack_topup_as_paid(): void
    {
        $data    = $this->createPendingTopup(['messages' => 50]);
        $handler = new HandleCurzzoTopupPaid();
        $handler->matches('inv_topup_123');

        $handler->handle([], 'evt_1', 'PAID');

        $data['topup']->refresh();
        $this->assertEquals(CurzzoTopup::STATUS_PAID, $data['topup']->status);
        $this->assertNotNull($data['topup']->paid_at);
        // Message pack should NOT get expires_at
        $this->assertNull($data['topup']->expires_at);
    }

    // ── handle() with PAID status — day pass ────────────────────────────────

    public function test_day_pass_gets_24_hour_expiry(): void
    {
        $data    = $this->createPendingTopup(['messages' => 0]);
        $handler = new HandleCurzzoTopupPaid();
        $handler->matches('inv_topup_123');

        $handler->handle([], 'evt_1', 'PAID');

        $data['topup']->refresh();
        $this->assertEquals(CurzzoTopup::STATUS_PAID, $data['topup']->status);
        $this->assertNotNull($data['topup']->expires_at);
        // Should be approximately 24 hours from now
        $this->assertTrue($data['topup']->expires_at->isAfter(now()->addHours(23)));
        $this->assertTrue($data['topup']->expires_at->isBefore(now()->addHours(25)));
    }

    // ── handle() with SETTLED status ────────────────────────────────────────

    public function test_settled_status_is_treated_as_paid(): void
    {
        $data    = $this->createPendingTopup();
        $handler = new HandleCurzzoTopupPaid();
        $handler->matches('inv_topup_123');

        $handler->handle([], 'evt_1', 'SETTLED');

        $data['topup']->refresh();
        $this->assertEquals(CurzzoTopup::STATUS_PAID, $data['topup']->status);
    }

    // ── Non-PAID statuses ───────────────────────────────────────────────────

    public function test_ignores_expired_status(): void
    {
        $data    = $this->createPendingTopup();
        $handler = new HandleCurzzoTopupPaid();
        $handler->matches('inv_topup_123');

        $handler->handle([], 'evt_1', 'EXPIRED');

        $data['topup']->refresh();
        $this->assertEquals(CurzzoTopup::STATUS_PENDING, $data['topup']->status);
        $this->assertNull($data['topup']->paid_at);
    }

    public function test_ignores_failed_status(): void
    {
        $data    = $this->createPendingTopup();
        $handler = new HandleCurzzoTopupPaid();
        $handler->matches('inv_topup_123');

        $handler->handle([], 'evt_1', 'FAILED');

        $data['topup']->refresh();
        $this->assertEquals(CurzzoTopup::STATUS_PENDING, $data['topup']->status);
    }

    public function test_ignores_unknown_status(): void
    {
        $data    = $this->createPendingTopup();
        $handler = new HandleCurzzoTopupPaid();
        $handler->matches('inv_topup_123');

        $handler->handle([], 'evt_1', 'AUTHORISED');

        $data['topup']->refresh();
        $this->assertEquals(CurzzoTopup::STATUS_PENDING, $data['topup']->status);
    }

    // ── Error handling ──────────────────────────────────────────────────────

    public function test_handle_without_matches_causes_error(): void
    {
        // Calling handle() without matches() means $topup is null
        $handler = new HandleCurzzoTopupPaid();

        $this->expectException(\Throwable::class);

        $handler->handle([], 'evt_1', 'PAID');
    }

    // ── Logging ─────────────────────────────────────────────────────────────

    public function test_logs_info_on_successful_payment(): void
    {
        $data = $this->createPendingTopup(['messages' => 0]);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($msg, $context) use ($data) {
                return str_contains($msg, 'curzzo topup paid')
                    && $context['topup_id'] === $data['topup']->id
                    && $context['day_pass'] === true;
            });

        $handler = new HandleCurzzoTopupPaid();
        $handler->matches('inv_topup_123');
        $handler->handle([], 'evt_1', 'PAID');
    }

    public function test_logs_error_and_rethrows_when_update_fails(): void
    {
        $data    = $this->createPendingTopup();
        $handler = new HandleCurzzoTopupPaid();
        $handler->matches('inv_topup_123');

        // Drop the curzzo_topups table to force the update to throw.
        \Illuminate\Support\Facades\Schema::drop('curzzo_topups');

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($msg, $context) use ($data) {
                return str_contains($msg, 'HandleCurzzoTopupPaid failed')
                    && $context['topup_id'] === $data['topup']->id
                    && isset($context['error']);
            });

        $this->expectException(\Throwable::class);

        try {
            $handler->handle([], 'evt_1', 'PAID');
        } finally {
            // Restore schema for RefreshDatabase teardown
            \Illuminate\Support\Facades\Artisan::call('migrate:fresh');
        }
    }
}
