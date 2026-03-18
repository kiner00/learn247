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
use App\Models\Subscription;
use App\Queries\Affiliate\GetAffiliateStats;
use App\Queries\Payout\CalculateEligibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AffiliateController extends Controller
{
    public function index(Request $request, GetAffiliateStats $stats, CalculateEligibility $eligibility): Response
    {
        $user        = $request->user();
        $period      = $request->get('period', 'month');
        $communityId = $request->get('community');
        $tab         = $request->get('tab', 'links');

        $activeRequestsByAffiliate = PayoutRequest::where('user_id', $user->id)
            ->where('type', PayoutRequest::TYPE_AFFILIATE)
            ->whereIn('status', [PayoutRequest::STATUS_PENDING, PayoutRequest::STATUS_APPROVED])
            ->get()->keyBy('affiliate_id');

        $rawAffiliates = $stats->getAffiliates($user);

        $affiliates = $rawAffiliates->map(fn ($a) => [
            'id'                    => $a->id,
            'code'                  => $a->code,
            'status'                => $a->status,
            'is_active'             => $a->isActive(),
            'total_earned'          => $a->total_earned,
            'total_paid'            => $a->total_paid,
            'pending_amount'        => $a->pendingAmount(),
            'eligible_amount'       => $eligibility->forAffiliate($a),
            'payout_request_status' => $activeRequestsByAffiliate->has($a->id) ? $activeRequestsByAffiliate->get($a->id)->status : null,
            'referral_url'          => url("/ref/{$a->code}"),
            'community'             => ['name' => $a->community->name, 'slug' => $a->community->slug],
            'facebook_pixel_id'     => $a->facebook_pixel_id,
            'tiktok_pixel_id'       => $a->tiktok_pixel_id,
            'google_analytics_id'   => $a->google_analytics_id,
        ]);

        $allAffiliateIds = $affiliates->pluck('id');
        $filteredIds = $communityId
            ? Affiliate::where('user_id', $user->id)->where('community_id', $communityId)->pluck('id')
            : $allAffiliateIds;

        $summary     = $stats->summary($filteredIds, $period);
        $conversions = $stats->conversions($filteredIds, $period, 100);

        $from = $this->periodStart($period);
        $avgPerReferral = $summary['total_conversions'] > 0 ? round($summary['total_earned'] / $summary['total_conversions'], 2) : 0;

        $bestMonth = AffiliateConversion::whereIn('affiliate_id', $allAffiliateIds)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(commission_amount) as total")
            ->groupBy('month')->orderByDesc('total')->first();

        $chartData = $this->buildChart($filteredIds, $period, $from);

        $communities = $affiliates->map(fn ($a) => ['id' => $a['community']['slug'], 'name' => $a['community']['name']]);

        $byComm = AffiliateConversion::whereIn('affiliate_id', $allAffiliateIds)
            ->select('affiliate_id', DB::raw('SUM(commission_amount) as total'))
            ->with('affiliate.community:id,name')->groupBy('affiliate_id')->get()
            ->map(fn ($c) => ['community' => $c->affiliate->community->name, 'total' => (float) $c->total])
            ->sortByDesc('total')->values();

        return Inertia::render('Affiliates/Index', [
            'affiliates'    => $affiliates,
            'payoutMethod'  => $user->payout_method,
            'payoutDetails' => $user->payout_details,
            'period'        => $period,
            'communityId'   => $communityId,
            'tab'           => $tab,
            'analytics'     => [
                'summary' => array_merge($summary, ['avg_per_referral' => $avgPerReferral, 'best_month' => $bestMonth?->month, 'best_month_total' => (float) ($bestMonth?->total ?? 0)]),
                'chartData' => $chartData, 'conversions' => $conversions, 'communities' => $communities, 'byComm' => $byComm,
            ],
        ]);
    }

    public function analytics(Request $request, GetAffiliateStats $stats): Response
    {
        $user        = $request->user();
        $period      = $request->get('period', 'month');
        $communityId = $request->get('community');

        $affiliateQuery = Affiliate::where('user_id', $user->id);
        if ($communityId) {
            $affiliateQuery->where('community_id', $communityId);
        }
        $affiliateIds = $affiliateQuery->pluck('id');

        $summary     = $stats->summary($affiliateIds, $period);
        $conversions = $stats->conversions($affiliateIds, $period, 100);

        $avgPerReferral = $summary['total_conversions'] > 0 ? round($summary['total_earned'] / $summary['total_conversions'], 2) : 0;

        $bestMonth = AffiliateConversion::whereIn('affiliate_id', $affiliateIds)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(commission_amount) as total")
            ->groupBy('month')->orderByDesc('total')->first();

        $from      = $this->periodStart($period);
        $chartData = $this->buildChart($affiliateIds, $period, $from);

        $communities = Affiliate::where('user_id', $user->id)->with('community:id,name,slug')->get()
            ->map(fn ($a) => ['id' => $a->community_id, 'name' => $a->community->name]);

        $byComm = AffiliateConversion::whereIn('affiliate_id', Affiliate::where('user_id', $user->id)->pluck('id'))
            ->select('affiliate_id', DB::raw('SUM(commission_amount) as total'))
            ->with('affiliate.community:id,name')->groupBy('affiliate_id')->get()
            ->map(fn ($c) => ['community' => $c->affiliate->community->name, 'total' => (float) $c->total])
            ->sortByDesc('total')->values();

        return Inertia::render('Affiliates/Analytics', [
            'period' => $period, 'communityId' => $communityId ? (int) $communityId : null,
            'summary' => array_merge($summary, ['avg_per_referral' => $avgPerReferral, 'best_month' => $bestMonth?->month, 'best_month_total' => (float) ($bestMonth?->total ?? 0)]),
            'chartData' => $chartData, 'conversions' => $conversions, 'communities' => $communities, 'byComm' => $byComm,
        ]);
    }

    public function store(Request $request, Community $community, JoinAffiliate $action): RedirectResponse
    {
        $action->execute($request->user(), $community);

        return back()->with('success', 'You are now an affiliate! Share your link to start earning.');
    }

    public function dashboard(Request $request, Community $community): Response
    {
        $this->authorize('update', $community);

        $affiliates = Affiliate::where('community_id', $community->id)->with('user')->latest()->get()
            ->map(fn ($a) => [
                'id' => $a->id, 'code' => $a->code, 'status' => $a->status,
                'total_earned' => $a->total_earned, 'total_paid' => $a->total_paid,
                'pending_amount' => $a->pendingAmount(), 'referral_url' => url("/ref/{$a->code}"),
                'user' => ['name' => $a->user->name, 'email' => $a->user->email],
                'payout_method' => $a->user->payout_method, 'payout_details' => $a->user->payout_details,
            ]);

        $conversions = AffiliateConversion::whereHas('affiliate', fn ($q) => $q->where('community_id', $community->id))
            ->with(['affiliate.user', 'referredUser'])->latest()->get()
            ->map(fn ($c) => [
                'id' => $c->id, 'date' => $c->created_at->format('M j, Y'),
                'referred_user' => $c->referredUser->name,
                'referred_email' => $c->referredUser->email,
                'referred_phone' => $c->referredUser->phone,
                'affiliate_name' => $c->affiliate->user->name,
                'sale_amount' => $c->sale_amount, 'platform_fee' => $c->platform_fee,
                'commission_amount' => $c->commission_amount, 'creator_amount' => $c->creator_amount,
                'status' => $c->status, 'paid_at' => $c->paid_at?->format('M j, Y'),
                'payout_method' => $c->affiliate->user->payout_method,
                'can_disburse' => DisbursePayout::supports($c->affiliate->user->payout_method ?? ''),
            ]);

        $affiliateIds = Affiliate::where('community_id', $community->id)->pluck('id');
        $abandonedLeads = Subscription::whereIn('affiliate_id', $affiliateIds)
            ->whereIn('status', [Subscription::STATUS_PENDING, Subscription::STATUS_EXPIRED])
            ->with('user:id,name,email,phone', 'affiliate.user:id,name')
            ->latest()->take(50)->get()
            ->map(fn ($s) => [
                'date'           => $s->created_at->format('M j, Y'),
                'name'           => $s->user?->name,
                'email'          => $s->user?->email,
                'phone'          => $s->user?->phone,
                'affiliate_name' => $s->affiliate?->user?->name,
                'status'         => $s->status,
            ]);

        $stats = ['total_affiliates' => $affiliates->count(), 'total_commissions' => $affiliates->sum('total_earned'), 'total_paid_out' => $affiliates->sum('total_paid')];

        return Inertia::render('Communities/Affiliates', compact('community', 'affiliates', 'conversions', 'stats', 'abandonedLeads'));
    }

    public function updatePayout(Request $request, Affiliate $affiliate): RedirectResponse
    {
        abort_unless($affiliate->user_id === $request->user()->id, 403);
        $data = $request->validate(['payout_method' => ['required', 'string', 'in:gcash,bank,paypal,maya'], 'payout_details' => ['required', 'string', 'max:255']]);
        $affiliate->update($data);

        return back()->with('success', 'Payout details saved.');
    }

    public function updatePixels(Request $request, Affiliate $affiliate): RedirectResponse
    {
        abort_unless($affiliate->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'facebook_pixel_id'   => ['nullable', 'string', 'regex:/^\d+$/', 'max:30'],
            'tiktok_pixel_id'     => ['nullable', 'string', 'max:30'],
            'google_analytics_id' => ['nullable', 'string', 'regex:/^G-[A-Z0-9]+$/i', 'max:20'],
        ]);
        $affiliate->update($data);

        return back()->with('success', 'Pixel IDs saved.');
    }

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

    public function markPaid(AffiliateConversion $conversion, MarkAffiliateConversionPaid $action): RedirectResponse
    {
        $this->authorize('update', $conversion->affiliate->community);
        if ($conversion->status === AffiliateConversion::STATUS_PAID) {
            return back()->with('error', 'Already marked as paid.');
        }
        $action->execute($conversion);

        return back()->with('success', 'Conversion marked as paid.');
    }

    private function periodStart(?string $period): ?Carbon
    {
        return match ($period) {
            'week' => Carbon::now()->startOfWeek(), 'month' => Carbon::now()->startOfMonth(),
            'year' => Carbon::now()->startOfYear(), default => null,
        };
    }

    private function buildChart(mixed $affiliateIds, string $period, ?Carbon $from): array
    {
        if (! $from) {
            $from = Carbon::now()->subYears(2);
        }

        if (in_array($period, ['week', 'month'])) {
            $days = $period === 'week' ? 7 : Carbon::now()->daysInMonth;
            $rows = AffiliateConversion::whereIn('affiliate_id', $affiliateIds)->where('created_at', '>=', $from)
                ->selectRaw("DATE(created_at) as label, SUM(commission_amount) as total")->groupBy('label')->pluck('total', 'label');
            $result = [];
            for ($i = 0; $i < $days; $i++) {
                $d = $from->copy()->addDays($i)->toDateString();
                $result[] = ['label' => Carbon::parse($d)->format('M j'), 'total' => (float) ($rows[$d] ?? 0)];
            }
            return $result;
        }

        $rows = AffiliateConversion::whereIn('affiliate_id', $affiliateIds)->where('created_at', '>=', $from)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as label, SUM(commission_amount) as total")->groupBy('label')->pluck('total', 'label');
        $result = [];
        $cursor = $from->copy()->startOfMonth();
        while ($cursor <= Carbon::now()) {
            $key = $cursor->format('Y-m');
            $result[] = ['label' => $cursor->format('M Y'), 'total' => (float) ($rows[$key] ?? 0)];
            $cursor->addMonth();
        }
        return $result;
    }
}
