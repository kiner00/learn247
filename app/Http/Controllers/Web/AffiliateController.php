<?php

namespace App\Http\Controllers\Web;

use App\Actions\Affiliate\DisbursePayout;
use App\Actions\Affiliate\JoinAffiliate;
use App\Actions\Affiliate\MarkAffiliateConversionPaid;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\PayoutRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AffiliateController extends Controller
{
    /** GET /my-affiliates — affiliate links + inline analytics */
    public function index(Request $request): Response
    {
        $user        = $request->user();
        $period      = $request->get('period', 'month');
        $communityId = $request->get('community');
        $tab         = $request->get('tab', 'links');

        $pendingRequestAffiliateIds = PayoutRequest::where('user_id', $user->id)
            ->where('type', PayoutRequest::TYPE_AFFILIATE)
            ->where('status', PayoutRequest::STATUS_PENDING)
            ->pluck('affiliate_id')
            ->flip();

        $affiliates = Affiliate::where('user_id', $user->id)
            ->with('community')
            ->latest()
            ->get()
            ->map(fn ($a) => [
                'id'                  => $a->id,
                'code'                => $a->code,
                'status'              => $a->status,
                'is_active'           => $a->isActive(),
                'total_earned'        => $a->total_earned,
                'total_paid'          => $a->total_paid,
                'pending_amount'      => $a->pendingAmount(),
                'eligible_amount'     => PayoutRequestController::affiliateEligibility($a),
                'has_pending_request' => $pendingRequestAffiliateIds->has($a->id),
                'referral_url'        => url("/ref/{$a->code}"),
                'community'           => [
                    'name' => $a->community->name,
                    'slug' => $a->community->slug,
                ],
            ]);

        // ── Analytics data ─────────────────────────────────────────────────────
        $allAffiliateIds = $affiliates->pluck('id');

        $filteredIds = $communityId
            ? Affiliate::where('user_id', $user->id)->where('community_id', $communityId)->pluck('id')
            : $allAffiliateIds;

        $from = match ($period) {
            'week'  => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'year'  => Carbon::now()->startOfYear(),
            default => null,
        };

        $base = AffiliateConversion::whereIn('affiliate_id', $filteredIds)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from));

        $totalEarned      = (float) (clone $base)->sum('commission_amount');
        $totalPaid        = (float) (clone $base)->where('status', AffiliateConversion::STATUS_PAID)->sum('commission_amount');
        $totalPending     = (float) (clone $base)->where('status', AffiliateConversion::STATUS_PENDING)->sum('commission_amount');
        $totalConversions = (clone $base)->count();
        $avgPerReferral   = $totalConversions > 0 ? round($totalEarned / $totalConversions, 2) : 0;

        $bestMonth = AffiliateConversion::whereIn('affiliate_id', $allAffiliateIds)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(commission_amount) as total")
            ->groupBy('month')
            ->orderByDesc('total')
            ->first();

        $chartData = match ($period) {
            'week'  => $this->chartByDay($filteredIds, $from, 7),
            'month' => $this->chartByDay($filteredIds, $from, Carbon::now()->daysInMonth),
            'year'  => $this->chartByMonth($filteredIds, $from),
            default => $this->chartByMonth($filteredIds, Carbon::now()->subYears(2)),
        };

        $conversions = (clone $base)
            ->with(['affiliate.community'])
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn ($c) => [
                'id'                => $c->id,
                'date'              => $c->created_at->format('M j, Y'),
                'community'         => $c->affiliate->community->name,
                'sale_amount'       => (float) $c->sale_amount,
                'commission_amount' => (float) $c->commission_amount,
                'status'            => $c->status,
                'paid_at'           => $c->paid_at?->format('M j, Y'),
            ]);

        $communities = $affiliates->map(fn ($a) => [
            'id'   => $a['community']['slug'],
            'name' => $a['community']['name'],
        ]);

        $byComm = AffiliateConversion::whereIn('affiliate_id', $allAffiliateIds)
            ->select('affiliate_id', DB::raw('SUM(commission_amount) as total'))
            ->with('affiliate.community:id,name')
            ->groupBy('affiliate_id')
            ->get()
            ->map(fn ($c) => [
                'community' => $c->affiliate->community->name,
                'total'     => (float) $c->total,
            ])
            ->sortByDesc('total')
            ->values();

        return Inertia::render('Affiliates/Index', [
            'affiliates'    => $affiliates,
            'payoutMethod'  => $user->payout_method,
            'payoutDetails' => $user->payout_details,
            'period'        => $period,
            'communityId'   => $communityId,
            'tab'           => $tab,
            'analytics'     => [
                'summary' => [
                    'total_earned'      => $totalEarned,
                    'total_paid'        => $totalPaid,
                    'total_pending'     => $totalPending,
                    'total_conversions' => $totalConversions,
                    'avg_per_referral'  => $avgPerReferral,
                    'best_month'        => $bestMonth?->month,
                    'best_month_total'  => (float) ($bestMonth?->total ?? 0),
                ],
                'chartData'   => $chartData,
                'conversions' => $conversions,
                'communities' => $communities,
                'byComm'      => $byComm,
            ],
        ]);
    }

    /** GET /my-affiliates/analytics */
    public function analytics(Request $request): Response
    {
        $user        = $request->user();
        $period      = $request->get('period', 'month');   // week|month|year|all
        $communityId = $request->get('community');          // null = all

        // All affiliate IDs for this user (optionally filtered by community)
        $affiliateQuery = Affiliate::where('user_id', $user->id);
        if ($communityId) {
            $affiliateQuery->where('community_id', $communityId);
        }
        $affiliateIds = $affiliateQuery->pluck('id');

        // Period bounds
        $from = match ($period) {
            'week'  => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'year'  => Carbon::now()->startOfYear(),
            default => null,
        };

        // Base conversion query
        $base = AffiliateConversion::whereIn('affiliate_id', $affiliateIds)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from));

        // ── Summary stats ──────────────────────────────────────────────────
        $totalEarned      = (float) (clone $base)->sum('commission_amount');
        $totalPaid        = (float) (clone $base)->where('status', AffiliateConversion::STATUS_PAID)->sum('commission_amount');
        $totalPending     = (float) (clone $base)->where('status', AffiliateConversion::STATUS_PENDING)->sum('commission_amount');
        $totalConversions = (clone $base)->count();
        $avgPerReferral   = $totalConversions > 0 ? round($totalEarned / $totalConversions, 2) : 0;

        // Best month (all time, regardless of period filter)
        $bestMonth = AffiliateConversion::whereIn('affiliate_id', $affiliateIds)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(commission_amount) as total")
            ->groupBy('month')
            ->orderByDesc('total')
            ->first();

        // ── Chart data — earnings grouped by sub-period ───────────────────
        $chartData = match ($period) {
            'week'  => $this->chartByDay($affiliateIds, $from, 7),
            'month' => $this->chartByDay($affiliateIds, $from, Carbon::now()->daysInMonth),
            'year'  => $this->chartByMonth($affiliateIds, $from),
            default => $this->chartByMonth($affiliateIds, Carbon::now()->subYear(2)),
        };

        // ── Conversion history ─────────────────────────────────────────────
        $conversions = (clone $base)
            ->with(['affiliate.community'])
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn ($c) => [
                'id'                => $c->id,
                'date'              => $c->created_at->format('M j, Y'),
                'community'         => $c->affiliate->community->name,
                'sale_amount'       => (float) $c->sale_amount,
                'commission_amount' => (float) $c->commission_amount,
                'status'            => $c->status,
                'paid_at'           => $c->paid_at?->format('M j, Y'),
            ]);

        // ── Community breakdown ────────────────────────────────────────────
        $communities = Affiliate::where('user_id', $user->id)
            ->with('community:id,name,slug')
            ->get()
            ->map(fn ($a) => [
                'id'   => $a->community_id,
                'name' => $a->community->name,
            ]);

        // ── Per-community earnings (all time) ──────────────────────────────
        $byComm = AffiliateConversion::whereIn('affiliate_id',
                    Affiliate::where('user_id', $user->id)->pluck('id'))
            ->select('affiliate_id', DB::raw('SUM(commission_amount) as total'))
            ->with('affiliate.community:id,name')
            ->groupBy('affiliate_id')
            ->get()
            ->map(fn ($c) => [
                'community' => $c->affiliate->community->name,
                'total'     => (float) $c->total,
            ])
            ->sortByDesc('total')
            ->values();

        return Inertia::render('Affiliates/Analytics', [
            'period'      => $period,
            'communityId' => $communityId ? (int) $communityId : null,
            'summary' => [
                'total_earned'      => $totalEarned,
                'total_paid'        => $totalPaid,
                'total_pending'     => $totalPending,
                'total_conversions' => $totalConversions,
                'avg_per_referral'  => $avgPerReferral,
                'best_month'        => $bestMonth?->month,
                'best_month_total'  => (float) ($bestMonth?->total ?? 0),
            ],
            'chartData'    => $chartData,
            'conversions'  => $conversions,
            'communities'  => $communities,
            'byComm'       => $byComm,
        ]);
    }

    private function chartByDay(mixed $affiliateIds, Carbon $from, int $days): array
    {
        $rows = AffiliateConversion::whereIn('affiliate_id', $affiliateIds)
            ->where('created_at', '>=', $from)
            ->selectRaw("DATE(created_at) as label, SUM(commission_amount) as total")
            ->groupBy('label')
            ->pluck('total', 'label');

        $result = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i)->toDateString();
            $result[] = ['label' => Carbon::parse($d)->format('M j'), 'total' => (float) ($rows[$d] ?? 0)];
        }
        return $result;
    }

    private function chartByMonth(mixed $affiliateIds, Carbon $from): array
    {
        $rows = AffiliateConversion::whereIn('affiliate_id', $affiliateIds)
            ->where('created_at', '>=', $from)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as label, SUM(commission_amount) as total")
            ->groupBy('label')
            ->pluck('total', 'label');

        $result = [];
        $cursor = $from->copy()->startOfMonth();
        while ($cursor <= Carbon::now()) {
            $key = $cursor->format('Y-m');
            $result[] = ['label' => $cursor->format('M Y'), 'total' => (float) ($rows[$key] ?? 0)];
            $cursor->addMonth();
        }
        return $result;
    }

    /** POST /communities/{community}/affiliates — join as affiliate */
    public function store(Request $request, Community $community, JoinAffiliate $action): RedirectResponse
    {
        $action->execute($request->user(), $community);

        return back()->with('success', 'You are now an affiliate! Share your link to start earning.');
    }

    /** GET /communities/{community}/affiliates — owner dashboard */
    public function dashboard(Request $request, Community $community): Response
    {
        $this->authorize('update', $community);

        $affiliates = Affiliate::where('community_id', $community->id)
            ->with('user')
            ->latest()
            ->get()
            ->map(fn ($a) => [
                'id'             => $a->id,
                'code'           => $a->code,
                'status'         => $a->status,
                'total_earned'   => $a->total_earned,
                'total_paid'     => $a->total_paid,
                'pending_amount' => $a->pendingAmount(),
                'referral_url'   => url("/ref/{$a->code}"),
                'user'           => ['name' => $a->user->name, 'email' => $a->user->email],
                'payout_method'  => $a->user->payout_method,
                'payout_details' => $a->user->payout_details,
            ]);

        $conversions = AffiliateConversion::whereHas('affiliate', fn ($q) => $q->where('community_id', $community->id))
            ->with(['affiliate.user', 'referredUser'])
            ->latest()
            ->get()
            ->map(fn ($c) => [
                'id'                => $c->id,
                'date'              => $c->created_at->format('M j, Y'),
                'referred_user'     => $c->referredUser->name,
                'affiliate_name'    => $c->affiliate->user->name,
                'sale_amount'       => $c->sale_amount,
                'platform_fee'      => $c->platform_fee,
                'commission_amount' => $c->commission_amount,
                'creator_amount'    => $c->creator_amount,
                'status'            => $c->status,
                'paid_at'           => $c->paid_at?->format('M j, Y'),
                'payout_method'     => $c->affiliate->user->payout_method,
                'can_disburse'      => DisbursePayout::supports($c->affiliate->user->payout_method ?? ''),
            ]);

        $stats = [
            'total_affiliates'    => $affiliates->count(),
            'total_commissions'   => $affiliates->sum('total_earned'),
            'total_paid_out'      => $affiliates->sum('total_paid'),
        ];

        return Inertia::render('Communities/Affiliates', compact('community', 'affiliates', 'conversions', 'stats'));
    }

    /** PATCH /affiliates/{affiliate}/payout — affiliate sets their payout details */
    public function updatePayout(Request $request, Affiliate $affiliate): RedirectResponse
    {
        abort_unless($affiliate->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'payout_method'  => ['required', 'string', 'in:gcash,bank,paypal,maya'],
            'payout_details' => ['required', 'string', 'max:255'],
        ]);

        $affiliate->update($data);

        return back()->with('success', 'Payout details saved.');
    }

    /** POST /affiliate-conversions/{conversion}/disburse — pay via Xendit + mark paid */
    public function disburse(AffiliateConversion $conversion, DisbursePayout $disburse, MarkAffiliateConversionPaid $mark): RedirectResponse
    {
        $this->authorize('update', $conversion->affiliate->community);

        if ($conversion->status === AffiliateConversion::STATUS_PAID) {
            return back()->with('error', 'Already paid.');
        }

        try {
            $disburse->execute($conversion);
            $mark->execute($conversion);
            return back()->with('success', 'Payout sent via Xendit and marked as paid.');
        } catch (\RuntimeException $e) {
            return back()->with('error', 'Xendit disbursement failed: ' . $e->getMessage());
        }
    }

    /** PATCH /affiliate-conversions/{conversion}/paid */
    public function markPaid(AffiliateConversion $conversion, MarkAffiliateConversionPaid $action): RedirectResponse
    {
        $this->authorize('update', $conversion->affiliate->community);

        if ($conversion->status === AffiliateConversion::STATUS_PAID) {
            return back()->with('error', 'Already marked as paid.');
        }

        $action->execute($conversion);

        return back()->with('success', 'Conversion marked as paid.');
    }
}
