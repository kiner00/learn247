<?php

namespace App\Http\Controllers\Web;

use App\Actions\Affiliate\DisbursePayout;
use App\Actions\Affiliate\JoinAffiliate;
use App\Actions\Affiliate\MarkAffiliateConversionPaid;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\CreatorPlanAffiliateApplication;
use App\Models\Setting;
use App\Queries\Affiliate\GetAffiliateDashboard;
use App\Queries\Affiliate\GetAffiliateStats;
use App\Queries\Payout\CalculateEligibility;
use App\Services\Affiliate\AffiliateChartService;
use App\Services\Wallet\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AffiliateController extends Controller
{
    public function index(Request $request, GetAffiliateStats $stats, CalculateEligibility $eligibility, AffiliateChartService $chart, WalletService $wallet): Response
    {
        $user = $request->user();
        $period = $request->query('period', 'month');
        $communityId = $request->query('community');
        $tab = $request->query('tab', 'links');

        $affiliates = $stats->mapForDashboard($user, $eligibility);
        $allAffiliateIds = $affiliates->pluck('id');
        $filteredIds = $communityId
            ? Affiliate::where('user_id', $user->id)->where('community_id', $communityId)->pluck('id')
            : $allAffiliateIds;

        $summary = $stats->summary($filteredIds, $period);
        $conversions = $stats->conversions($filteredIds, $period, 100);

        $from = $chart->periodStart($period);
        $avgPerReferral = $summary['total_conversions'] > 0 ? round($summary['total_earned'] / $summary['total_conversions'], 2) : 0;
        $bestMonth = $chart->bestMonth($allAffiliateIds);
        $chartData = $chart->buildChart($filteredIds, $period, $from);
        $communities = $affiliates->map(fn ($a) => ['id' => $a['community']['slug'], 'name' => $a['community']['name']]);
        $byComm = $chart->byComm($allAffiliateIds);

        return Inertia::render('Affiliates/Index', [
            'affiliates' => $affiliates,
            'payoutMethod' => $user->payout_method,
            'payoutDetails' => $user->payout_details,
            'period' => $period,
            'communityId' => $communityId,
            'tab' => $tab,
            'wallet' => $wallet->balanceOf($user),
            'withdrawals' => $stats->withdrawals($user->id),
            'creatorPlanAffiliate' => $this->creatorPlanAffiliateState($user),
            'analytics' => [
                'summary' => array_merge($summary, ['avg_per_referral' => $avgPerReferral, 'best_month' => $bestMonth?->month, 'best_month_total' => (float) ($bestMonth?->total ?? 0)]),
                'chartData' => $chartData, 'conversions' => $conversions, 'communities' => $communities, 'byComm' => $byComm,
            ],
        ]);
    }

    private function creatorPlanAffiliateState($user): array
    {
        $affiliate = Affiliate::creatorPlan()->where('user_id', $user->id)->first();
        $application = CreatorPlanAffiliateApplication::where('user_id', $user->id)->latest()->first();

        $rate = (float) Setting::get('creator_plan_affiliate_commission_rate', 20);
        $maxMonths = (int) Setting::get('creator_plan_affiliate_max_months', 12);

        $state = 'apply';
        if ($affiliate) {
            $state = 'approved';
        } elseif ($application?->isPending()) {
            $state = 'pending';
        } elseif ($application?->status === CreatorPlanAffiliateApplication::STATUS_REJECTED) {
            $state = 'rejected';
        }

        return [
            'state' => $state,
            'commission_rate' => $rate,
            'max_months' => $maxMonths,
            'code' => $affiliate?->code,
            'referral_url' => $affiliate ? url('/ref/'.$affiliate->code) : null,
            'rejection_reason' => $state === 'rejected' ? $application?->rejection_reason : null,
        ];
    }

    public function analytics(Request $request, GetAffiliateStats $stats, AffiliateChartService $chart): Response
    {
        $user = $request->user();
        $period = $request->query('period', 'month');
        $communityId = $request->query('community');

        $affiliateQuery = Affiliate::where('user_id', $user->id);
        if ($communityId) {
            $affiliateQuery->where('community_id', $communityId);
        }
        $affiliateIds = $affiliateQuery->pluck('id');

        $summary = $stats->summary($affiliateIds, $period);
        $conversions = $stats->conversions($affiliateIds, $period, 100);

        $avgPerReferral = $summary['total_conversions'] > 0 ? round($summary['total_earned'] / $summary['total_conversions'], 2) : 0;
        $from = $chart->periodStart($period);
        $bestMonth = $chart->bestMonth($affiliateIds);
        $chartData = $chart->buildChart($affiliateIds, $period, $from);

        $communities = Affiliate::where('user_id', $user->id)->with('community:id,name,slug')->get()
            ->map(fn ($a) => ['id' => $a->community_id, 'name' => $a->community->name]);

        $byComm = $chart->byComm(Affiliate::where('user_id', $user->id)->pluck('id'));

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

    public function dashboard(Request $request, Community $community, GetAffiliateDashboard $query): Response
    {
        $this->authorize('update', $community);

        $data = $query->execute($community);

        return Inertia::render('Communities/Affiliates', array_merge(['community' => $community], $data));
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
            'facebook_pixel_id' => ['nullable', 'string', 'regex:/^\d+$/', 'max:30'],
            'tiktok_pixel_id' => ['nullable', 'string', 'max:30'],
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
            return back()->with('error', 'Xendit disbursement failed: '.$e->getMessage());
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
}
