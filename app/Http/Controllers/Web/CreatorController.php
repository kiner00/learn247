<?php

namespace App\Http\Controllers\Web;

use App\Actions\Billing\StartCreatorPlanCheckout;
use App\Http\Controllers\Controller;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\OwnerPayout;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\Setting;
use App\Models\Subscription;
use App\Queries\Payout\CalculateEligibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CreatorController extends Controller
{
    public function plan(): Response
    {
        $user = Auth::user();

        return Inertia::render('Creator/Plan', [
            'regularPrice'    => (float) Setting::get('creator_plan_regular_price', 3000),
            'discountedPrice' => (float) Setting::get('creator_plan_discounted_price', 1999),
            'isProActive'     => $user->hasActiveCreatorPlan(),
        ]);
    }

    public function planCheckout(StartCreatorPlanCheckout $action): RedirectResponse
    {
        $user   = Auth::user();
        $result = $action->execute($user);

        return redirect()->away($result['checkout_url']);
    }

    public function dashboard(CalculateEligibility $eligibility): Response
    {
        $user = Auth::user();

        $communities = Community::where('owner_id', $user->id)
            ->where('price', '>', 0)
            ->withCount('members')
            ->get()
            ->map(function (Community $community) use ($eligibility) {
                [$eligibleNow, $lockedAmount, $nextEligibleDate] = $eligibility->forOwner($community);

                $gross = (float) Payment::where('community_id', $community->id)->where('status', Payment::STATUS_PAID)->sum('amount');
                $affiliateCommission = (float) AffiliateConversion::whereHas('affiliate', fn ($q) => $q->where('community_id', $community->id))->sum('commission_amount');
                $platformFee = round($gross * 0.15, 2);
                $earned      = round($gross - $platformFee - $affiliateCommission, 2);
                $paid        = (float) OwnerPayout::where('community_id', $community->id)->where('status', '!=', 'failed')->sum('amount');

                $pendingRequest = PayoutRequest::where('community_id', $community->id)
                    ->where('type', PayoutRequest::TYPE_OWNER)->where('status', PayoutRequest::STATUS_PENDING)->first();

                $recentPayments = Payment::where('community_id', $community->id)->where('status', Payment::STATUS_PAID)
                    ->with('user:id,name,email,phone')->latest('paid_at')->take(10)->get()
                    ->map(fn ($p) => ['member_name' => $p->user?->name, 'member_email' => $p->user?->email, 'member_phone' => $p->user?->phone, 'amount' => (float) $p->amount, 'paid_at' => $p->paid_at?->toDateString()]);

                $abandonedPayments = Subscription::where('community_id', $community->id)
                    ->whereIn('status', [Subscription::STATUS_PENDING, Subscription::STATUS_EXPIRED])
                    ->with('user:id,name,email,phone')
                    ->latest()->take(20)->get()
                    ->map(fn ($s) => ['name' => $s->user?->name, 'email' => $s->user?->email, 'phone' => $s->user?->phone, 'status' => $s->status, 'date' => $s->created_at->toDateString()]);

                return [
                    'community_id'       => $community->id,
                    'community_name'     => $community->name,
                    'community_slug'     => $community->slug,
                    'members_count'      => $community->members_count,
                    'gross'              => $gross,
                    'platform_fee'       => $platformFee,
                    'commissions'        => $affiliateCommission,
                    'earned'             => $earned,
                    'paid'               => $paid,
                    'eligible_now'       => $eligibleNow,
                    'locked_amount'      => $lockedAmount,
                    'next_eligible_date' => $nextEligibleDate,
                    'pending_request'    => $pendingRequest ? ['id' => $pendingRequest->id, 'amount' => (float) $pendingRequest->amount] : null,
                    'recent_payments'    => $recentPayments,
                    'abandoned_payments' => $abandonedPayments,
                ];
            });

        $requestHistory = PayoutRequest::where('user_id', $user->id)->where('type', PayoutRequest::TYPE_OWNER)
            ->with('community:id,name')->latest()->take(20)->get()
            ->map(fn ($r) => [
                'id' => $r->id, 'community_name' => $r->community?->name, 'amount' => (float) $r->amount,
                'status' => $r->status, 'rejection_reason' => $r->rejection_reason,
                'requested_at' => $r->created_at->toDateString(), 'processed_at' => $r->processed_at?->toDateString(),
            ]);

        $analytics = null;
        if ($user->hasActiveCreatorPlan()) {
            $analytics = $this->buildAnalytics($user->id);
        }

        return Inertia::render('Creator/Dashboard', [
            'communities'    => $communities,
            'requestHistory' => $requestHistory,
            'payoutMethod'   => $user->payout_method,
            'payoutDetails'  => $user->payout_details,
            'analytics'      => $analytics,
            'isPro'          => $user->hasActiveCreatorPlan(),
        ]);
    }

    private function buildAnalytics(int $userId): array
    {
        $communityIds = Community::where('owner_id', $userId)->pluck('id');

        // Last 6 months labels
        $months = collect(range(5, 0))->map(fn ($i) => Carbon::now()->subMonths($i));
        $labels = $months->map(fn ($m) => $m->format('M Y'))->values()->toArray();

        // Monthly revenue
        $revenueRaw = Payment::whereIn('community_id', $communityIds)
            ->where('status', Payment::STATUS_PAID)
            ->where('paid_at', '>=', Carbon::now()->subMonths(6)->startOfMonth())
            ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as month, SUM(amount) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $revenue = $months->map(fn ($m) => (float) ($revenueRaw[$m->format('Y-m')] ?? 0))->values()->toArray();

        // New members per month
        $newMembersRaw = Subscription::whereIn('community_id', $communityIds)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('created_at', '>=', Carbon::now()->subMonths(6)->startOfMonth())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $newMembers = $months->map(fn ($m) => (int) ($newMembersRaw[$m->format('Y-m')] ?? 0))->values()->toArray();

        // Churn per month (expired/cancelled subscriptions)
        $churnRaw = Subscription::whereIn('community_id', $communityIds)
            ->whereIn('status', [Subscription::STATUS_EXPIRED, Subscription::STATUS_CANCELLED])
            ->where('updated_at', '>=', Carbon::now()->subMonths(6)->startOfMonth())
            ->selectRaw("DATE_FORMAT(updated_at, '%Y-%m') as month, COUNT(*) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $churn = $months->map(fn ($m) => (int) ($churnRaw[$m->format('Y-m')] ?? 0))->values()->toArray();

        // Retention rate (active / (active + expired in last 30d))
        $active30  = Subscription::whereIn('community_id', $communityIds)->where('status', Subscription::STATUS_ACTIVE)->count();
        $expired30 = Subscription::whereIn('community_id', $communityIds)
            ->whereIn('status', [Subscription::STATUS_EXPIRED, Subscription::STATUS_CANCELLED])
            ->where('updated_at', '>=', Carbon::now()->subDays(30))
            ->count();

        $retentionRate = ($active30 + $expired30) > 0
            ? round(($active30 / ($active30 + $expired30)) * 100, 1)
            : 100.0;

        // MRR (Monthly Recurring Revenue) = sum of active subscription community prices
        $mrr = Subscription::whereIn('community_id', $communityIds)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->join('communities', 'subscriptions.community_id', '=', 'communities.id')
            ->sum('communities.price');

        return compact('labels', 'revenue', 'newMembers', 'churn', 'retentionRate', 'mrr');
    }
}
