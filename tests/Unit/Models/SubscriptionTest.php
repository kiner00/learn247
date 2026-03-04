<?php

namespace Tests\Unit\Models;

use App\Models\Subscription;
use Carbon\Carbon;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    public function test_is_active_returns_true_for_active_status_with_future_expiry(): void
    {
        $sub             = new Subscription();
        $sub->status     = Subscription::STATUS_ACTIVE;
        $sub->expires_at = Carbon::now()->addMonth();

        $this->assertTrue($sub->isActive());
    }

    public function test_is_active_returns_true_for_active_status_with_null_expiry(): void
    {
        $sub             = new Subscription();
        $sub->status     = Subscription::STATUS_ACTIVE;
        $sub->expires_at = null;

        $this->assertTrue($sub->isActive());
    }

    public function test_is_active_returns_false_for_active_status_with_past_expiry(): void
    {
        $sub             = new Subscription();
        $sub->status     = Subscription::STATUS_ACTIVE;
        $sub->expires_at = Carbon::now()->subDay();

        $this->assertFalse($sub->isActive());
    }

    public function test_is_active_returns_false_for_pending_status(): void
    {
        $sub         = new Subscription();
        $sub->status = Subscription::STATUS_PENDING;

        $this->assertFalse($sub->isActive());
    }

    public function test_is_active_returns_false_for_expired_status(): void
    {
        $sub         = new Subscription();
        $sub->status = Subscription::STATUS_EXPIRED;

        $this->assertFalse($sub->isActive());
    }

    public function test_is_active_returns_false_for_cancelled_status(): void
    {
        $sub         = new Subscription();
        $sub->status = Subscription::STATUS_CANCELLED;

        $this->assertFalse($sub->isActive());
    }
}
