<?php

namespace Tests\Feature\Queries;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Queries\Affiliate\GetAffiliateAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GetAffiliateAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private GetAffiliateAnalytics $query;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $pdo = DB::connection()->getPdo();
            $pdo->sqliteCreateFunction('DATE_FORMAT', function ($date, $format) {
                $map = ['%Y' => 'Y', '%m' => 'm', '%d' => 'd', '%Y-%m' => 'Y-m'];
                return date($map[$format] ?? 'Y-m-d', strtotime($date));
            }, 2);
        }

        $this->query = new GetAffiliateAnalytics();
    }

    // ─── helpers ───────────────────────────────────────────────────────────────

    private function createConversion(Affiliate $affiliate, float $saleAmount, float $commission, string $status = AffiliateConversion::STATUS_PENDING, ?Carbon $createdAt = null): AffiliateConversion
    {
        $sub = Subscription::create([
            'community_id'       => $affiliate->community_id,
            'user_id'            => User::factory()->create()->id,
            'status'             => Subscription::STATUS_ACTIVE,
            'xendit_id'          => 'inv_' . fake()->unique()->uuid(),
            'xendit_invoice_url' => 'https://checkout.xendit.co/' . fake()->uuid(),
            'expires_at'         => now()->addMonth(),
        ]);

        $payment = Payment::create([
            'subscription_id'    => $sub->id,
            'community_id'       => $affiliate->community_id,
            'user_id'            => $sub->user_id,
            'amount'             => $saleAmount,
            'currency'           => 'PHP',
            'status'             => Payment::STATUS_PAID,
            'metadata'           => [],
            'paid_at'            => now(),
        ]);

        $conversion = AffiliateConversion::create([
            'affiliate_id'      => $affiliate->id,
            'subscription_id'   => $sub->id,
            'payment_id'        => $payment->id,
            'referred_user_id'  => $sub->user_id,
            'sale_amount'       => $saleAmount,
            'platform_fee'      => $saleAmount * 0.15,
            'commission_amount' => $commission,
            'creator_amount'    => $saleAmount - ($saleAmount * 0.15) - $commission,
            'status'            => $status,
            'paid_at'           => $status === AffiliateConversion::STATUS_PAID ? now() : null,
        ]);

        if ($createdAt) {
            $conversion->forceFill(['created_at' => $createdAt])->save();
        }

        return $conversion;
    }

    private function createAffiliate(User $user, Community $community, string $code): Affiliate
    {
        return Affiliate::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'code'         => $code,
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);
    }

    // ─── execute — summary ─────────────────────────────────────────────────────

    public function test_returns_correct_structure(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $this->createAffiliate($user, $community, 'STRUCT01');

        $result = $this->query->execute($user);

        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('chartData', $result);
        $this->assertArrayHasKey('conversions', $result);
        $this->assertArrayHasKey('communities', $result);
        $this->assertArrayHasKey('byComm', $result);
    }

    public function test_summary_with_no_conversions(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $this->createAffiliate($user, $community, 'EMPTY01');

        $result = $this->query->execute($user);

        $this->assertEquals(0, $result['summary']['total_earned']);
        $this->assertEquals(0, $result['summary']['total_paid']);
        $this->assertEquals(0, $result['summary']['total_pending']);
        $this->assertEquals(0, $result['summary']['total_conversions']);
        $this->assertEquals(0, $result['summary']['avg_per_referral']);
        $this->assertNull($result['summary']['best_month']);
    }

    public function test_summary_totals_are_calculated_correctly(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate = $this->createAffiliate($user, $community, 'TOTAL01');

        $this->createConversion($affiliate, 500, 50, AffiliateConversion::STATUS_PENDING);
        $this->createConversion($affiliate, 1000, 100, AffiliateConversion::STATUS_PAID);
        $this->createConversion($affiliate, 800, 80, AffiliateConversion::STATUS_PENDING);

        $result = $this->query->execute($user, 'month');

        $this->assertEquals(230.0, $result['summary']['total_earned']);
        $this->assertEquals(100.0, $result['summary']['total_paid']);
        $this->assertEquals(130.0, $result['summary']['total_pending']);
        $this->assertEquals(3, $result['summary']['total_conversions']);
        $this->assertEquals(76.67, $result['summary']['avg_per_referral']);
    }

    public function test_summary_best_month_across_all_affiliates(): void
    {
        $user  = User::factory()->create();
        $comm1 = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $comm2 = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $aff1  = $this->createAffiliate($user, $comm1, 'BEST01');
        $aff2  = $this->createAffiliate($user, $comm2, 'BEST02');

        $lastMonth = Carbon::now()->subMonth()->startOfMonth()->addDays(5);
        $this->createConversion($aff1, 2000, 200, AffiliateConversion::STATUS_PAID, $lastMonth);
        $this->createConversion($aff2, 500, 50, AffiliateConversion::STATUS_PENDING);

        $result = $this->query->execute($user, 'year');

        $this->assertNotNull($result['summary']['best_month']);
        $this->assertEquals($lastMonth->format('Y-m'), $result['summary']['best_month']);
        $this->assertEquals(200.0, $result['summary']['best_month_total']);
    }

    // ─── execute — filter by community ─────────────────────────────────────────

    public function test_filters_by_community_id(): void
    {
        $user  = User::factory()->create();
        $comm1 = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $comm2 = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $aff1  = $this->createAffiliate($user, $comm1, 'FILT01');
        $aff2  = $this->createAffiliate($user, $comm2, 'FILT02');

        $this->createConversion($aff1, 1000, 100, AffiliateConversion::STATUS_PENDING);
        $this->createConversion($aff2, 2000, 200, AffiliateConversion::STATUS_PENDING);

        $result = $this->query->execute($user, 'month', $comm1->id);

        $this->assertEquals(100.0, $result['summary']['total_earned']);
        $this->assertEquals(1, $result['summary']['total_conversions']);
    }

    // ─── execute — period filter ───────────────────────────────────────────────

    public function test_week_period_excludes_old_conversions(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate = $this->createAffiliate($user, $community, 'WEEK01');

        $this->createConversion($affiliate, 1000, 100, AffiliateConversion::STATUS_PENDING);
        $this->createConversion($affiliate, 500, 50, AffiliateConversion::STATUS_PENDING, Carbon::now()->subWeeks(2));

        $result = $this->query->execute($user, 'week');

        $this->assertEquals(100.0, $result['summary']['total_earned']);
        $this->assertEquals(1, $result['summary']['total_conversions']);
    }

    public function test_year_period_excludes_old_conversions(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate = $this->createAffiliate($user, $community, 'YEAR01');

        $this->createConversion($affiliate, 1000, 100, AffiliateConversion::STATUS_PENDING);
        $this->createConversion($affiliate, 500, 50, AffiliateConversion::STATUS_PENDING, Carbon::now()->subYears(2));

        $result = $this->query->execute($user, 'year');

        $this->assertEquals(100.0, $result['summary']['total_earned']);
    }

    public function test_all_time_period_includes_everything(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate = $this->createAffiliate($user, $community, 'ALLTIME');

        $this->createConversion($affiliate, 1000, 100, AffiliateConversion::STATUS_PENDING);
        $this->createConversion($affiliate, 500, 50, AffiliateConversion::STATUS_PENDING, Carbon::now()->subYears(3));

        $result = $this->query->execute($user, 'all');

        $this->assertEquals(150.0, $result['summary']['total_earned']);
        $this->assertEquals(2, $result['summary']['total_conversions']);
    }

    // ─── execute — chartData ───────────────────────────────────────────────────

    public function test_chart_data_for_week_period(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate = $this->createAffiliate($user, $community, 'CHART01');

        $this->createConversion($affiliate, 500, 50, AffiliateConversion::STATUS_PENDING);

        $result = $this->query->execute($user, 'week');

        $this->assertCount(7, $result['chartData']);
        $this->assertArrayHasKey('label', $result['chartData'][0]);
        $this->assertArrayHasKey('total', $result['chartData'][0]);
    }

    public function test_chart_data_for_month_period(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate = $this->createAffiliate($user, $community, 'CHART02');

        $this->createConversion($affiliate, 500, 50, AffiliateConversion::STATUS_PENDING);

        $result = $this->query->execute($user, 'month');

        $this->assertCount(Carbon::now()->daysInMonth, $result['chartData']);
    }

    public function test_chart_data_for_year_has_monthly_labels(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate = $this->createAffiliate($user, $community, 'CHART03');

        $this->createConversion($affiliate, 500, 50, AffiliateConversion::STATUS_PENDING);

        $result = $this->query->execute($user, 'year');

        $this->assertNotEmpty($result['chartData']);
        $this->assertArrayHasKey('label', $result['chartData'][0]);
        $this->assertArrayHasKey('total', $result['chartData'][0]);
    }

    // ─── execute — conversions list ────────────────────────────────────────────

    public function test_conversions_list_structure(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate = $this->createAffiliate($user, $community, 'LIST01');

        $this->createConversion($affiliate, 500, 50, AffiliateConversion::STATUS_PENDING);

        $result = $this->query->execute($user, 'month');

        $this->assertCount(1, $result['conversions']);
        $conv = $result['conversions'][0];
        $this->assertArrayHasKey('id', $conv);
        $this->assertArrayHasKey('date', $conv);
        $this->assertArrayHasKey('community', $conv);
        $this->assertArrayHasKey('sale_amount', $conv);
        $this->assertArrayHasKey('commission_amount', $conv);
        $this->assertArrayHasKey('status', $conv);
        $this->assertArrayHasKey('paid_at', $conv);
    }

    public function test_conversions_limited_to_100(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate = $this->createAffiliate($user, $community, 'LIM100');

        for ($i = 0; $i < 105; $i++) {
            $this->createConversion($affiliate, 100, 10, AffiliateConversion::STATUS_PENDING);
        }

        $result = $this->query->execute($user, 'month');

        $this->assertCount(100, $result['conversions']);
    }

    // ─── execute — communities list ────────────────────────────────────────────

    public function test_communities_list_returned(): void
    {
        $user  = User::factory()->create();
        $comm1 = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $comm2 = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $this->createAffiliate($user, $comm1, 'COMML01');
        $this->createAffiliate($user, $comm2, 'COMML02');

        $result = $this->query->execute($user);

        $this->assertCount(2, $result['communities']);
        $this->assertArrayHasKey('id', $result['communities'][0]);
        $this->assertArrayHasKey('name', $result['communities'][0]);
    }

    // ─── execute — byComm breakdown ────────────────────────────────────────────

    public function test_by_community_breakdown(): void
    {
        $user  = User::factory()->create();
        $comm1 = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $comm2 = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $aff1  = $this->createAffiliate($user, $comm1, 'BYCOM01');
        $aff2  = $this->createAffiliate($user, $comm2, 'BYCOM02');

        $this->createConversion($aff1, 1000, 100, AffiliateConversion::STATUS_PENDING);
        $this->createConversion($aff2, 2000, 200, AffiliateConversion::STATUS_PENDING);

        $result = $this->query->execute($user);

        $this->assertCount(2, $result['byComm']);
        $this->assertEquals($comm2->name, $result['byComm'][0]['community']);
        $this->assertEquals(200.0, $result['byComm'][0]['total']);
        $this->assertEquals($comm1->name, $result['byComm'][1]['community']);
        $this->assertEquals(100.0, $result['byComm'][1]['total']);
    }

    // ─── user with no affiliates ───────────────────────────────────────────────

    public function test_user_with_no_affiliates_returns_empty_data(): void
    {
        $user = User::factory()->create();

        $result = $this->query->execute($user);

        $this->assertEquals(0, $result['summary']['total_earned']);
        $this->assertEquals(0, $result['summary']['total_conversions']);
        $this->assertEmpty($result['conversions']);
        $this->assertEmpty($result['communities']);
        $this->assertEmpty($result['byComm']);
    }
}
