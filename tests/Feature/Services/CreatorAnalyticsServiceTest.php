<?php

namespace Tests\Feature\Services;

use App\Models\Community;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Analytics\CreatorAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CreatorAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private CreatorAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CreatorAnalyticsService;

        // SQLite does not have DATE_FORMAT — register a compatible shim
        DB::connection()->getPdo()->sqliteCreateFunction('DATE_FORMAT', function ($date, $format) {
            $map = ['%Y' => 'Y', '%m' => 'm', '%d' => 'd', '%H' => 'H', '%i' => 'i', '%s' => 's'];

            return date(strtr($format, $map), strtotime($date));
        }, 2);
    }

    // ── Structure ─────────────────────────────────────────────────────────────

    public function test_returns_all_expected_keys(): void
    {
        $user = User::factory()->create();
        $result = $this->service->build($user->id);

        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('revenue', $result);
        $this->assertArrayHasKey('newMembers', $result);
        $this->assertArrayHasKey('churn', $result);
        $this->assertArrayHasKey('retentionRate', $result);
        $this->assertArrayHasKey('mrr', $result);
    }

    public function test_returns_six_labels(): void
    {
        $user = User::factory()->create();
        $result = $this->service->build($user->id);

        $this->assertCount(6, $result['labels']);
        $this->assertCount(6, $result['revenue']);
        $this->assertCount(6, $result['newMembers']);
        $this->assertCount(6, $result['churn']);
    }

    public function test_labels_are_formatted_as_month_year(): void
    {
        $user = User::factory()->create();
        $result = $this->service->build($user->id);

        // Labels should look like "Jan 2026", "Feb 2026" etc.
        foreach ($result['labels'] as $label) {
            $this->assertMatchesRegularExpression('/^[A-Z][a-z]{2} \d{4}$/', $label);
        }
    }

    // ── Retention rate edge cases ─────────────────────────────────────────────

    public function test_retention_rate_defaults_to_100_when_no_subscriptions(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);

        $result = $this->service->build($user->id);

        $this->assertSame(100.0, $result['retentionRate']);
    }

    public function test_retention_rate_is_100_when_all_subscriptions_active(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);

        Subscription::factory()->active()->count(3)->create(['community_id' => $community->id]);

        $result = $this->service->build($user->id);

        $this->assertSame(100.0, $result['retentionRate']);
    }

    public function test_retention_rate_calculated_with_active_and_expired(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);

        Subscription::factory()->active()->count(3)->create(['community_id' => $community->id]);

        Subscription::factory()->create([
            'community_id' => $community->id,
            'status' => Subscription::STATUS_EXPIRED,
            'updated_at' => now()->subDays(10), // within 30-day window
        ]);

        $result = $this->service->build($user->id);

        // active=3, expired=1 → rate = 3/4 * 100 = 75.0
        $this->assertSame(75.0, $result['retentionRate']);
    }

    public function test_retention_rate_ignores_churn_older_than_30_days(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);

        Subscription::factory()->active()->count(2)->create(['community_id' => $community->id]);

        Subscription::factory()->create([
            'community_id' => $community->id,
            'status' => Subscription::STATUS_EXPIRED,
            'updated_at' => now()->subDays(45), // outside 30-day window
        ]);

        $result = $this->service->build($user->id);

        // Only 2 active, expired outside window ignored → 2/2 = 100%
        $this->assertSame(100.0, $result['retentionRate']);
    }

    // ── MRR ───────────────────────────────────────────────────────────────────

    public function test_mrr_is_zero_with_no_communities(): void
    {
        $user = User::factory()->create();
        $result = $this->service->build($user->id);

        $this->assertEquals(0, $result['mrr']);
    }

    public function test_mrr_sums_active_subscription_community_prices(): void
    {
        $owner = User::factory()->create();
        $c1 = Community::factory()->create(['owner_id' => $owner->id, 'price' => 200]);
        $c2 = Community::factory()->create(['owner_id' => $owner->id, 'price' => 300]);

        Subscription::factory()->active()->create(['community_id' => $c1->id]);
        Subscription::factory()->active()->create(['community_id' => $c2->id]);

        $result = $this->service->build($owner->id);

        $this->assertEquals(500, $result['mrr']);
    }

    public function test_mrr_excludes_expired_subscriptions(): void
    {
        $owner = User::factory()->create();
        $c1 = Community::factory()->create(['owner_id' => $owner->id, 'price' => 200]);

        Subscription::factory()->create([
            'community_id' => $c1->id,
            'status' => Subscription::STATUS_EXPIRED,
        ]);

        $result = $this->service->build($owner->id);

        $this->assertEquals(0, $result['mrr']);
    }

    public function test_only_includes_own_community_data(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $ownCommunity = Community::factory()->create(['owner_id' => $owner->id, 'price' => 100]);
        $otherCommunity = Community::factory()->create(['owner_id' => $other->id, 'price' => 500]);

        Subscription::factory()->active()->create(['community_id' => $ownCommunity->id]);
        Subscription::factory()->active()->create(['community_id' => $otherCommunity->id]);

        $result = $this->service->build($owner->id);

        $this->assertEquals(100, $result['mrr']);
    }

    public function test_revenue_and_member_arrays_are_floats_and_ints(): void
    {
        $user = User::factory()->create();
        $result = $this->service->build($user->id);

        foreach ($result['revenue'] as $value) {
            $this->assertIsFloat($value);
        }
        foreach ($result['newMembers'] as $value) {
            $this->assertIsInt($value);
        }
        foreach ($result['churn'] as $value) {
            $this->assertIsInt($value);
        }
    }
}
