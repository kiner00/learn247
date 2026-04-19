<?php

namespace Tests\Feature\Services;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Affiliate\AffiliateChartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AffiliateChartServiceTest extends TestCase
{
    use RefreshDatabase;

    private AffiliateChartService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AffiliateChartService;

        // SQLite does not have DATE_FORMAT — register a compatible shim
        DB::connection()->getPdo()->sqliteCreateFunction('DATE_FORMAT', function ($date, $format) {
            $map = ['%Y' => 'Y', '%m' => 'm', '%d' => 'd', '%H' => 'H', '%i' => 'i', '%s' => 's'];

            return date(strtr($format, $map), strtotime($date));
        }, 2);
    }

    // ── periodStart ───────────────────────────────────────────────────────────

    public function test_period_start_week_returns_start_of_current_week(): void
    {
        $result = $this->service->periodStart('week');

        $this->assertNotNull($result);
        $this->assertEquals(Carbon::now()->startOfWeek()->toDateString(), $result->toDateString());
    }

    public function test_period_start_month_returns_start_of_current_month(): void
    {
        $result = $this->service->periodStart('month');

        $this->assertNotNull($result);
        $this->assertEquals(Carbon::now()->startOfMonth()->toDateString(), $result->toDateString());
    }

    public function test_period_start_year_returns_start_of_current_year(): void
    {
        $result = $this->service->periodStart('year');

        $this->assertNotNull($result);
        $this->assertEquals(Carbon::now()->startOfYear()->toDateString(), $result->toDateString());
    }

    public function test_period_start_null_input_returns_null(): void
    {
        $this->assertNull($this->service->periodStart(null));
    }

    public function test_period_start_unknown_value_returns_null(): void
    {
        $this->assertNull($this->service->periodStart('quarterly'));
    }

    // ── buildChart (week — uses DATE() which works in SQLite) ─────────────────

    public function test_build_chart_week_returns_seven_data_points(): void
    {
        $from = Carbon::now()->startOfWeek();
        $result = $this->service->buildChart(collect([]), 'week', $from);

        $this->assertCount(7, $result);
    }

    public function test_build_chart_week_each_point_has_label_and_total(): void
    {
        $from = Carbon::now()->startOfWeek();
        $result = $this->service->buildChart(collect([]), 'week', $from);

        foreach ($result as $point) {
            $this->assertArrayHasKey('label', $point);
            $this->assertArrayHasKey('total', $point);
            $this->assertIsFloat($point['total']);
        }
    }

    public function test_build_chart_week_totals_zero_with_no_conversions(): void
    {
        $from = Carbon::now()->startOfWeek();
        $result = $this->service->buildChart(collect([999]), 'week', $from);

        foreach ($result as $point) {
            $this->assertSame(0.0, $point['total']);
        }
    }

    public function test_build_chart_week_includes_conversion_amounts(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $affUser = User::factory()->create();
        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $affUser->id,
            'code' => 'CHT01',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);
        $member = User::factory()->create();
        $sub = Subscription::factory()->active()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'subscription_id' => $sub->id,
            'referred_user_id' => $member->id,
            'sale_amount' => 1000,
            'platform_fee' => 98,
            'commission_amount' => 100,
            'creator_amount' => 802,
            'created_at' => Carbon::now()->startOfWeek()->addDay(),
        ]);

        $from = Carbon::now()->startOfWeek();
        $result = $this->service->buildChart([$affiliate->id], 'week', $from);

        $totalCommission = collect($result)->sum('total');
        $this->assertEquals(100.0, $totalCommission);
    }

    public function test_build_chart_month_returns_days_in_current_month(): void
    {
        $from = Carbon::now()->startOfMonth();
        $result = $this->service->buildChart(collect([]), 'month', $from);
        $expected = Carbon::now()->daysInMonth;

        $this->assertCount($expected, $result);
    }

    public function test_build_chart_null_from_uses_two_year_fallback(): void
    {
        // With null $from, buildChart defaults to 2 years ago → monthly chart
        $result = $this->service->buildChart(collect([]), 'year', null);

        // Should have at least 24 months of data points
        $this->assertGreaterThanOrEqual(24, count($result));
    }

    // ── byComm ────────────────────────────────────────────────────────────────

    public function test_by_comm_returns_empty_collection_with_no_conversions(): void
    {
        $result = $this->service->byComm([999]);

        $this->assertTrue($result->isEmpty());
    }

    public function test_by_comm_groups_by_affiliate_community(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'name' => 'Test Community']);
        $affUser = User::factory()->create();
        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $affUser->id,
            'code' => 'BYC01',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);
        $member = User::factory()->create();
        $sub = Subscription::factory()->active()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'subscription_id' => $sub->id,
            'referred_user_id' => $member->id,
            'sale_amount' => 500,
            'platform_fee' => 49,
            'commission_amount' => 50,
            'creator_amount' => 401,
        ]);

        AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'subscription_id' => $sub->id,
            'referred_user_id' => $member->id,
            'sale_amount' => 500,
            'platform_fee' => 49,
            'commission_amount' => 75,
            'creator_amount' => 376,
        ]);

        $result = $this->service->byComm([$affiliate->id]);

        $this->assertCount(1, $result);
        $this->assertSame('Test Community', $result->first()['community']);
        $this->assertEquals(125.0, $result->first()['total']);
    }

    public function test_by_comm_only_includes_given_affiliate_ids(): void
    {
        $owner = User::factory()->create();
        $c1 = Community::factory()->create(['owner_id' => $owner->id, 'name' => 'C1']);
        $c2 = Community::factory()->create(['owner_id' => $owner->id, 'name' => 'C2']);
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $aff1 = Affiliate::create(['community_id' => $c1->id, 'user_id' => $u1->id, 'code' => 'A1', 'status' => Affiliate::STATUS_ACTIVE]);
        $aff2 = Affiliate::create(['community_id' => $c2->id, 'user_id' => $u2->id, 'code' => 'A2', 'status' => Affiliate::STATUS_ACTIVE]);

        $member = User::factory()->create();
        $sub1 = Subscription::factory()->active()->create(['community_id' => $c1->id, 'user_id' => $member->id]);
        $sub2 = Subscription::factory()->active()->create(['community_id' => $c2->id, 'user_id' => $member->id]);

        AffiliateConversion::create(['affiliate_id' => $aff1->id, 'subscription_id' => $sub1->id, 'referred_user_id' => $member->id,
            'sale_amount' => 100, 'platform_fee' => 10, 'commission_amount' => 20, 'creator_amount' => 70]);

        AffiliateConversion::create(['affiliate_id' => $aff2->id, 'subscription_id' => $sub2->id, 'referred_user_id' => $member->id,
            'sale_amount' => 100, 'platform_fee' => 10, 'commission_amount' => 30, 'creator_amount' => 60]);

        // Only query aff1
        $result = $this->service->byComm([$aff1->id]);

        $this->assertCount(1, $result);
        $this->assertSame('C1', $result->first()['community']);
    }

    public function test_by_comm_sorted_by_total_descending(): void
    {
        $owner = User::factory()->create();
        $c1 = Community::factory()->create(['owner_id' => $owner->id, 'name' => 'Low Earner']);
        $c2 = Community::factory()->create(['owner_id' => $owner->id, 'name' => 'High Earner']);
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $aff1 = Affiliate::create(['community_id' => $c1->id, 'user_id' => $u1->id, 'code' => 'S1', 'status' => Affiliate::STATUS_ACTIVE]);
        $aff2 = Affiliate::create(['community_id' => $c2->id, 'user_id' => $u2->id, 'code' => 'S2', 'status' => Affiliate::STATUS_ACTIVE]);

        $member = User::factory()->create();
        $sub1 = Subscription::factory()->active()->create(['community_id' => $c1->id, 'user_id' => $member->id]);
        $sub2 = Subscription::factory()->active()->create(['community_id' => $c2->id, 'user_id' => $member->id]);

        AffiliateConversion::create(['affiliate_id' => $aff1->id, 'subscription_id' => $sub1->id, 'referred_user_id' => $member->id,
            'sale_amount' => 100, 'platform_fee' => 10, 'commission_amount' => 10, 'creator_amount' => 80]);

        AffiliateConversion::create(['affiliate_id' => $aff2->id, 'subscription_id' => $sub2->id, 'referred_user_id' => $member->id,
            'sale_amount' => 100, 'platform_fee' => 10, 'commission_amount' => 90, 'creator_amount' => 0]);

        $result = $this->service->byComm([$aff1->id, $aff2->id]);

        $this->assertSame('High Earner', $result->first()['community']);
    }
}
