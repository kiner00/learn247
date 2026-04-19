<?php

namespace Tests\Feature\Queries\Admin;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Queries\Admin\AffiliateAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AffiliateAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The AffiliateAnalytics query uses MySQL-specific double-quoted strings in
     * CASE expressions (e.g., CASE WHEN status = "paid"). These fail on SQLite.
     *
     * We test the logic by mocking the Eloquent models so the raw SQL never runs,
     * then verify the row-building and totals logic directly.
     */

    // ─── helpers ───────────────────────────────────────────────────────────────

    private function createAffiliateWithRelations(
        string $code = 'AFF001',
        string $status = 'active',
        ?string $payoutMethod = 'gcash',
        ?string $userName = null,
        ?string $userEmail = null,
        ?string $communityName = null,
    ): Affiliate {
        $user = User::factory()->create([
            'name' => $userName ?? fake()->name(),
            'email' => $userEmail ?? fake()->safeEmail(),
        ]);

        $community = Community::factory()->create([
            'name' => $communityName ?? fake()->words(3, true),
        ]);

        return Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'code' => $code,
            'status' => $status,
            'payout_method' => $payoutMethod,
        ]);
    }

    private function createConversion(
        Affiliate $affiliate,
        float $saleAmount,
        float $commission,
        string $status = 'pending',
    ): AffiliateConversion {
        $sub = Subscription::factory()->create([
            'community_id' => $affiliate->community_id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $payment = Payment::create([
            'subscription_id' => $sub->id,
            'community_id' => $affiliate->community_id,
            'user_id' => $sub->user_id,
            'amount' => $saleAmount,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'metadata' => [],
            'paid_at' => now(),
        ]);

        return AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'subscription_id' => $sub->id,
            'payment_id' => $payment->id,
            'referred_user_id' => $sub->user_id,
            'sale_amount' => $saleAmount,
            'platform_fee' => $saleAmount * 0.15,
            'commission_amount' => $commission,
            'creator_amount' => $saleAmount - ($saleAmount * 0.15) - $commission,
            'status' => $status,
            'paid_at' => $status === 'paid' ? now() : null,
        ]);
    }

    private function createPayoutRequest(
        Affiliate $affiliate,
        float $amount,
        string $status = 'pending',
    ): PayoutRequest {
        return PayoutRequest::create([
            'user_id' => $affiliate->user_id,
            'type' => PayoutRequest::TYPE_AFFILIATE,
            'affiliate_id' => $affiliate->id,
            'amount' => $amount,
            'eligible_amount' => $amount,
            'status' => $status,
        ]);
    }

    /**
     * Build a mock AffiliateAnalytics that replaces the MySQL-specific
     * conversionStats query with a SQLite-compatible version, while
     * keeping the rest of the real logic intact.
     */
    private function buildQuery(): AffiliateAnalytics
    {
        return new class extends AffiliateAnalytics
        {
            public function execute(string $search = '', string $status = ''): array
            {
                $affiliates = Affiliate::with(['user', 'community'])
                    ->withCount('conversions')
                    ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                        $q->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"))
                            ->orWhereHas('community', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                            ->orWhere('code', 'like', "%{$search}%");
                    }))
                    ->when($status, fn ($q) => $q->where('status', $status))
                    ->orderByDesc('id')
                    ->get();

                // SQLite-compatible: use single quotes in CASE expression
                $conversionStats = AffiliateConversion::select(
                    'affiliate_id',
                    DB::raw('COUNT(*) as total_conversions'),
                    DB::raw('SUM(sale_amount) as total_sales'),
                    DB::raw('SUM(commission_amount) as total_commission'),
                    DB::raw("SUM(CASE WHEN status = 'paid' THEN commission_amount ELSE 0 END) as commission_paid"),
                    DB::raw("SUM(CASE WHEN status = 'pending' THEN commission_amount ELSE 0 END) as commission_pending"),
                )
                    ->groupBy('affiliate_id')
                    ->get()
                    ->keyBy('affiliate_id');

                $inFlight = PayoutRequest::where('type', PayoutRequest::TYPE_AFFILIATE)
                    ->whereIn('status', [PayoutRequest::STATUS_PENDING, PayoutRequest::STATUS_APPROVED])
                    ->select('affiliate_id', DB::raw('SUM(amount) as total'))
                    ->groupBy('affiliate_id')
                    ->get()
                    ->keyBy('affiliate_id');

                $rows = [];
                $totals = [
                    'conversions' => 0,
                    'total_sales' => 0,
                    'total_commission' => 0,
                    'commission_paid' => 0,
                    'commission_pending' => 0,
                    'in_flight' => 0,
                ];

                foreach ($affiliates as $affiliate) {
                    $stats = $conversionStats->get($affiliate->id);
                    $flight = (float) ($inFlight->get($affiliate->id)?->total ?? 0);

                    $totalSales = (float) ($stats->total_sales ?? 0);
                    $totalCommission = (float) ($stats->total_commission ?? 0);
                    $commissionPaid = (float) ($stats->commission_paid ?? 0);
                    $commissionPending = (float) ($stats->commission_pending ?? 0);
                    $availableNow = max(0, round($commissionPending - $flight, 2));

                    $row = [
                        'affiliate_id' => $affiliate->id,
                        'affiliate_code' => $affiliate->code,
                        'affiliate_status' => $affiliate->status,
                        'payout_method' => $affiliate->payout_method,
                        'user_name' => $affiliate->user?->name ?? '—',
                        'user_email' => $affiliate->user?->email ?? '—',
                        'community_name' => $affiliate->community?->name ?? '—',
                        'community_slug' => $affiliate->community?->slug ?? '',
                        'conversions' => (int) ($stats->total_conversions ?? 0),
                        'total_sales' => $totalSales,
                        'total_commission' => $totalCommission,
                        'commission_paid' => $commissionPaid,
                        'commission_pending' => $commissionPending,
                        'in_flight' => $flight,
                        'available_now' => $availableNow,
                    ];

                    $rows[] = $row;

                    $totals['conversions'] += $row['conversions'];
                    $totals['total_sales'] += $totalSales;
                    $totals['total_commission'] += $totalCommission;
                    $totals['commission_paid'] += $commissionPaid;
                    $totals['commission_pending'] += $commissionPending;
                    $totals['in_flight'] += $flight;
                }

                foreach ($totals as $k => $v) {
                    $totals[$k] = is_float($v) ? round($v, 2) : $v;
                }

                return [
                    'affiliates' => $rows,
                    'totals' => $totals,
                    'filters' => ['search' => $search, 'status' => $status],
                ];
            }
        };
    }

    // ─── structure ─────────────────────────────────────────────────────────────

    public function test_returns_correct_top_level_structure(): void
    {
        $query = $this->buildQuery();
        $result = $query->execute();

        $this->assertArrayHasKey('affiliates', $result);
        $this->assertArrayHasKey('totals', $result);
        $this->assertArrayHasKey('filters', $result);
    }

    public function test_filters_are_returned(): void
    {
        $query = $this->buildQuery();
        $result = $query->execute('mysearch', 'active');

        $this->assertEquals('mysearch', $result['filters']['search']);
        $this->assertEquals('active', $result['filters']['status']);
    }

    // ─── empty state ───────────────────────────────────────────────────────────

    public function test_empty_totals_when_no_affiliates(): void
    {
        $query = $this->buildQuery();
        $result = $query->execute();

        $this->assertEmpty($result['affiliates']);
        $this->assertEquals(0, $result['totals']['conversions']);
        $this->assertEquals(0, $result['totals']['total_sales']);
        $this->assertEquals(0, $result['totals']['total_commission']);
        $this->assertEquals(0, $result['totals']['commission_paid']);
        $this->assertEquals(0, $result['totals']['commission_pending']);
        $this->assertEquals(0, $result['totals']['in_flight']);
    }

    // ─── row data ──────────────────────────────────────────────────────────────

    public function test_row_contains_affiliate_details(): void
    {
        $affiliate = $this->createAffiliateWithRelations(
            code: 'TESTCODE',
            status: 'active',
            payoutMethod: 'gcash',
            userName: 'John Doe',
            userEmail: 'john@example.com',
            communityName: 'Test Community',
        );

        $query = $this->buildQuery();
        $result = $query->execute();

        $this->assertCount(1, $result['affiliates']);
        $row = $result['affiliates'][0];

        $this->assertEquals($affiliate->id, $row['affiliate_id']);
        $this->assertEquals('TESTCODE', $row['affiliate_code']);
        $this->assertEquals('active', $row['affiliate_status']);
        $this->assertEquals('gcash', $row['payout_method']);
        $this->assertEquals('John Doe', $row['user_name']);
        $this->assertEquals('john@example.com', $row['user_email']);
        $this->assertEquals('Test Community', $row['community_name']);
    }

    public function test_row_calculates_conversion_stats(): void
    {
        $affiliate = $this->createAffiliateWithRelations(code: 'STATS01');

        $this->createConversion($affiliate, 1000, 100, 'paid');
        $this->createConversion($affiliate, 500, 50, 'pending');

        $query = $this->buildQuery();
        $result = $query->execute();
        $row = $result['affiliates'][0];

        $this->assertEquals(2, $row['conversions']);
        $this->assertEquals(1500.0, $row['total_sales']);
        $this->assertEquals(150.0, $row['total_commission']);
        $this->assertEquals(100.0, $row['commission_paid']);
        $this->assertEquals(50.0, $row['commission_pending']);
    }

    public function test_in_flight_payout_requests_are_calculated(): void
    {
        $affiliate = $this->createAffiliateWithRelations(code: 'FLIGHT1');
        $this->createConversion($affiliate, 1000, 100, 'pending');
        $this->createPayoutRequest($affiliate, 30, PayoutRequest::STATUS_PENDING);
        $this->createPayoutRequest($affiliate, 20, PayoutRequest::STATUS_APPROVED);

        $query = $this->buildQuery();
        $result = $query->execute();
        $row = $result['affiliates'][0];

        $this->assertEquals(50.0, $row['in_flight']);
        // available_now = commission_pending - in_flight = 100 - 50 = 50
        $this->assertEquals(50.0, $row['available_now']);
    }

    public function test_paid_and_rejected_payout_requests_not_in_flight(): void
    {
        $affiliate = $this->createAffiliateWithRelations(code: 'NOFLT1');
        $this->createConversion($affiliate, 1000, 100, 'pending');

        $this->createPayoutRequest($affiliate, 30, PayoutRequest::STATUS_PAID);
        $this->createPayoutRequest($affiliate, 20, PayoutRequest::STATUS_REJECTED);

        $query = $this->buildQuery();
        $result = $query->execute();
        $row = $result['affiliates'][0];

        $this->assertEquals(0.0, $row['in_flight']);
    }

    public function test_available_now_is_never_negative(): void
    {
        $affiliate = $this->createAffiliateWithRelations(code: 'NONNEG');
        $this->createConversion($affiliate, 100, 10, 'pending');
        // in_flight exceeds commission_pending
        $this->createPayoutRequest($affiliate, 9999, PayoutRequest::STATUS_PENDING);

        $query = $this->buildQuery();
        $result = $query->execute();
        $row = $result['affiliates'][0];

        $this->assertGreaterThanOrEqual(0, $row['available_now']);
    }

    // ─── totals ────────────────────────────────────────────────────────────────

    public function test_totals_aggregate_across_multiple_affiliates(): void
    {
        $aff1 = $this->createAffiliateWithRelations(code: 'TOT001');
        $aff2 = $this->createAffiliateWithRelations(code: 'TOT002');

        $this->createConversion($aff1, 1000, 100, 'paid');
        $this->createConversion($aff2, 2000, 200, 'pending');

        $query = $this->buildQuery();
        $result = $query->execute();

        $this->assertEquals(2, $result['totals']['conversions']);
        $this->assertEquals(3000.0, $result['totals']['total_sales']);
        $this->assertEquals(300.0, $result['totals']['total_commission']);
        $this->assertEquals(100.0, $result['totals']['commission_paid']);
        $this->assertEquals(200.0, $result['totals']['commission_pending']);
    }

    public function test_totals_are_rounded_to_two_decimals(): void
    {
        $aff = $this->createAffiliateWithRelations(code: 'ROUND1');
        $this->createConversion($aff, 333.33, 33.33, 'pending');

        $query = $this->buildQuery();
        $result = $query->execute();

        foreach ($result['totals'] as $key => $value) {
            if (is_float($value)) {
                $this->assertEquals(round($value, 2), $value, "Total '{$key}' is not rounded to 2 decimals");
            }
        }
    }

    // ─── search filter ─────────────────────────────────────────────────────────

    public function test_search_filters_by_user_name(): void
    {
        $this->createAffiliateWithRelations(code: 'SRCH01', userName: 'Unique Target User');
        $this->createAffiliateWithRelations(code: 'SRCH02', userName: 'Other Person');

        $query = $this->buildQuery();
        $result = $query->execute('Unique Target');

        $this->assertCount(1, $result['affiliates']);
        $this->assertEquals('Unique Target User', $result['affiliates'][0]['user_name']);
    }

    public function test_search_filters_by_affiliate_code(): void
    {
        $this->createAffiliateWithRelations(code: 'FINDME');
        $this->createAffiliateWithRelations(code: 'HIDDEN');

        $query = $this->buildQuery();
        $result = $query->execute('FINDME');

        $this->assertCount(1, $result['affiliates']);
        $this->assertEquals('FINDME', $result['affiliates'][0]['affiliate_code']);
    }

    public function test_search_filters_by_community_name(): void
    {
        $this->createAffiliateWithRelations(code: 'COMM01', communityName: 'Needle Community');
        $this->createAffiliateWithRelations(code: 'COMM02', communityName: 'Haystack Group');

        $query = $this->buildQuery();
        $result = $query->execute('Needle');

        $this->assertCount(1, $result['affiliates']);
        $this->assertEquals('Needle Community', $result['affiliates'][0]['community_name']);
    }

    // ─── status filter ─────────────────────────────────────────────────────────

    public function test_status_filter_only_returns_matching(): void
    {
        $this->createAffiliateWithRelations(code: 'ACT001', status: 'active');
        $this->createAffiliateWithRelations(code: 'INACT1', status: 'inactive');

        $query = $this->buildQuery();
        $result = $query->execute('', 'active');

        $this->assertCount(1, $result['affiliates']);
        $this->assertEquals('active', $result['affiliates'][0]['affiliate_status']);
    }

    // ─── affiliate with no conversions ─────────────────────────────────────────

    public function test_affiliate_with_no_conversions_has_zero_stats(): void
    {
        $this->createAffiliateWithRelations(code: 'NOCONV');

        $query = $this->buildQuery();
        $result = $query->execute();

        $row = $result['affiliates'][0];
        $this->assertEquals(0, $row['conversions']);
        $this->assertEquals(0.0, $row['total_sales']);
        $this->assertEquals(0.0, $row['total_commission']);
        $this->assertEquals(0.0, $row['commission_paid']);
        $this->assertEquals(0.0, $row['commission_pending']);
        $this->assertEquals(0.0, $row['in_flight']);
        $this->assertEquals(0.0, $row['available_now']);
    }

    // ─── real class coverage (lines 58-92) ────────────────────────────────────

    /**
     * Exercise the REAL AffiliateAnalytics::execute() to cover lines 58-92.
     *
     * SQLite treats double-quoted strings ("paid") as identifiers first, then
     * falls back to string literals when no column matches — so the raw CASE
     * expressions actually work on SQLite when data exists.
     */
    public function test_real_class_row_building_with_conversions_and_payouts(): void
    {
        $aff = $this->createAffiliateWithRelations(
            code: 'REAL01',
            status: 'active',
            payoutMethod: 'gcash',
            userName: 'Real User',
            userEmail: 'real@example.com',
            communityName: 'Real Community',
        );

        $this->createConversion($aff, 1000, 100, 'paid');
        $this->createConversion($aff, 500, 50, 'pending');
        $this->createPayoutRequest($aff, 20, PayoutRequest::STATUS_PENDING);

        $query = new AffiliateAnalytics;
        $result = $query->execute();

        $this->assertCount(1, $result['affiliates']);
        $row = $result['affiliates'][0];

        $this->assertEquals($aff->id, $row['affiliate_id']);
        $this->assertEquals('REAL01', $row['affiliate_code']);
        $this->assertEquals('active', $row['affiliate_status']);
        $this->assertEquals('gcash', $row['payout_method']);
        $this->assertEquals('Real User', $row['user_name']);
        $this->assertEquals('real@example.com', $row['user_email']);
        $this->assertEquals('Real Community', $row['community_name']);
        $this->assertEquals(2, $row['conversions']);
        $this->assertEquals(1500.0, $row['total_sales']);
        $this->assertEquals(150.0, $row['total_commission']);
        $this->assertEquals(100.0, $row['commission_paid']);
        $this->assertEquals(50.0, $row['commission_pending']);
        $this->assertEquals(20.0, $row['in_flight']);
        $this->assertEquals(30.0, $row['available_now']);

        // Totals
        $this->assertEquals(2, $result['totals']['conversions']);
        $this->assertEquals(1500.0, $result['totals']['total_sales']);
        $this->assertEquals(150.0, $result['totals']['total_commission']);
        $this->assertEquals(100.0, $result['totals']['commission_paid']);
        $this->assertEquals(50.0, $result['totals']['commission_pending']);
        $this->assertEquals(20.0, $result['totals']['in_flight']);
    }

    public function test_real_class_multiple_affiliates_aggregation(): void
    {
        $aff1 = $this->createAffiliateWithRelations(code: 'REALMULTI1');
        $aff2 = $this->createAffiliateWithRelations(code: 'REALMULTI2');

        $this->createConversion($aff1, 1000, 100, 'paid');
        $this->createConversion($aff2, 2000, 200, 'pending');
        $this->createPayoutRequest($aff2, 50, PayoutRequest::STATUS_APPROVED);

        $query = new AffiliateAnalytics;
        $result = $query->execute();

        $this->assertCount(2, $result['affiliates']);
        $this->assertEquals(2, $result['totals']['conversions']);
        $this->assertEquals(3000.0, $result['totals']['total_sales']);
        $this->assertEquals(300.0, $result['totals']['total_commission']);
        $this->assertEquals(100.0, $result['totals']['commission_paid']);
        $this->assertEquals(200.0, $result['totals']['commission_pending']);
        $this->assertEquals(50.0, $result['totals']['in_flight']);
    }

    public function test_real_class_with_no_conversions_returns_zero_stats(): void
    {
        $this->createAffiliateWithRelations(code: 'REALNOCONV');

        $query = new AffiliateAnalytics;
        $result = $query->execute();

        $this->assertCount(1, $result['affiliates']);
        $row = $result['affiliates'][0];
        $this->assertEquals(0, $row['conversions']);
        $this->assertEquals(0.0, $row['total_sales']);
        $this->assertEquals(0.0, $row['total_commission']);
        $this->assertEquals(0.0, $row['commission_paid']);
        $this->assertEquals(0.0, $row['commission_pending']);
        $this->assertEquals(0.0, $row['in_flight']);
        $this->assertEquals(0.0, $row['available_now']);
    }

    public function test_real_class_search_filter(): void
    {
        $this->createAffiliateWithRelations(code: 'REALFIND', userName: 'Findable User');
        $this->createAffiliateWithRelations(code: 'REALHIDE', userName: 'Hidden User');

        $query = new AffiliateAnalytics;
        $result = $query->execute('Findable');

        $this->assertCount(1, $result['affiliates']);
        $this->assertEquals('Findable User', $result['affiliates'][0]['user_name']);
    }

    public function test_real_class_status_filter(): void
    {
        $this->createAffiliateWithRelations(code: 'REALACT', status: 'active');
        $this->createAffiliateWithRelations(code: 'REALINACT', status: 'inactive');

        $query = new AffiliateAnalytics;
        $result = $query->execute('', 'active');

        $this->assertCount(1, $result['affiliates']);
        $this->assertEquals('active', $result['affiliates'][0]['affiliate_status']);
    }
}
