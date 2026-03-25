<?php

namespace App\Http\Controllers\Web;

use App\Actions\Billing\StartCreatorPlanCheckout;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\Setting;
use App\Models\Subscription;
use App\Queries\Payout\CalculateEligibility;
use App\Services\Analytics\CreatorAnalyticsService;
use App\Services\Payout\OwnerEarningsCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CreatorController extends Controller
{
    public function plan(): Response
    {
        $user = Auth::user();

        return Inertia::render('Creator/Plan', [
            'basicPrice'   => (float) Setting::get('creator_plan_basic_price', 499),
            'proPrice'     => (float) Setting::get('creator_plan_pro_price', 1999),
            'currentPlan'  => $user->creatorPlan(),
        ]);
    }

    public function planCheckout(Request $request, StartCreatorPlanCheckout $action)
    {
        $user   = Auth::user();
        $plan   = $request->validate(['plan' => ['required', 'in:basic,pro']])['plan'];
        $result = $action->execute($user, $plan);

        return response()->json(['checkout_url' => $result['checkout_url']]);
    }

    public function dashboard(CalculateEligibility $eligibility, OwnerEarningsCalculator $earningsCalc, CreatorAnalyticsService $analyticsService): Response
    {
        $user = Auth::user();

        $communities = Community::where('owner_id', $user->id)
            ->where('price', '>', 0)
            ->withCount('members')
            ->get()
            ->map(function (Community $community) use ($eligibility, $earningsCalc) {
                [$eligibleNow, $lockedAmount, $nextEligibleDate] = $eligibility->forOwner($community);

                $e = $earningsCalc->forCommunity($community);

                $pendingRequest = PayoutRequest::where('community_id', $community->id)
                    ->where('type', PayoutRequest::TYPE_OWNER)
                    ->whereIn('status', [PayoutRequest::STATUS_PENDING, PayoutRequest::STATUS_APPROVED])
                    ->latest()->first();

                $recentPayments = Payment::where('community_id', $community->id)->where('status', Payment::STATUS_PAID)
                    ->with('user:id,name,email,phone')->latest('paid_at')->take(10)->get()
                    ->map(fn ($p) => ['member_name' => $p->user?->name, 'member_email' => $p->user?->email, 'member_phone' => $p->user?->phone, 'amount' => (float) $p->amount, 'paid_at' => $p->paid_at?->toDateString()]);

                $abandonedPayments = Subscription::where('community_id', $community->id)
                    ->whereIn('status', [Subscription::STATUS_PENDING, Subscription::STATUS_EXPIRED])
                    ->with('user:id,name,email,phone')
                    ->latest()->take(20)->get()
                    ->map(fn ($s) => ['name' => $s->user?->name, 'email' => $s->user?->email, 'phone' => $s->user?->phone, 'status' => $s->status, 'date' => $s->created_at->toDateString()]);

                return [
                    'community_id'        => $community->id,
                    'community_name'      => $community->name,
                    'community_slug'      => $community->slug,
                    'members_count'       => $community->members_count,
                    'gross'               => $e['gross'],
                    'platform_fee'        => $e['platform_fee'],
                    'platform_fee_rate'   => $e['platform_fee_rate'],
                    'payout_fee'          => Community::PAYOUT_FEE,
                    'commissions'         => $e['affiliate_commission'],
                    'earned'              => $e['earned'],
                    'paid'                => $e['paid'],
                    'eligible_now'        => $eligibleNow,
                    'locked_amount'       => $lockedAmount,
                    'next_eligible_date'  => $nextEligibleDate,
                    'pending_request'     => $pendingRequest ? ['id' => $pendingRequest->id, 'amount' => (float) $pendingRequest->amount, 'status' => $pendingRequest->status] : null,
                    'recent_payments'     => $recentPayments,
                    'abandoned_payments'  => $abandonedPayments,
                ];
            });

        $requestHistory = PayoutRequest::where('user_id', $user->id)->where('type', PayoutRequest::TYPE_OWNER)
            ->with('community:id,name')->latest()->take(20)->get()
            ->map(fn ($r) => [
                'id' => $r->id, 'community_name' => $r->community?->name, 'amount' => (float) $r->amount,
                'status' => $r->status, 'rejection_reason' => $r->rejection_reason,
                'requested_at' => $r->created_at->toDateString(), 'processed_at' => $r->processed_at?->toDateString(),
            ]);

        $currentPlan = $user->creatorPlan();
        $analytics   = in_array($currentPlan, ['basic', 'pro']) ? $analyticsService->build($user->id) : null;

        return Inertia::render('Creator/Dashboard', [
            'communities'    => $communities,
            'requestHistory' => $requestHistory,
            'payoutMethod'   => $user->payout_method,
            'payoutDetails'  => $user->payout_details,
            'analytics'      => $analytics,
            'currentPlan'    => $currentPlan,
            'payoutFee'      => Community::PAYOUT_FEE,
        ]);
    }
}
