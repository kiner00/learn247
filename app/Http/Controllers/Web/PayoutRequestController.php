<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\OwnerPayout;
use App\Models\Payment;
use App\Models\PayoutRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PayoutRequestController extends Controller
{
    /**
     * Community owner submits a payout request.
     */
    public function storeOwner(Request $request, Community $community): RedirectResponse
    {
        $owner = Auth::user();

        abort_unless($community->owner_id === $owner->id, 403);
        abort_unless(
            in_array($owner->payout_method, ['gcash', 'maya']) && $owner->payout_details,
            422,
            'Please set your payout method in Account Settings before requesting a payout.'
        );

        // Block if there's already a pending request for this community
        $hasPending = PayoutRequest::where('community_id', $community->id)
            ->where('type', PayoutRequest::TYPE_OWNER)
            ->where('status', PayoutRequest::STATUS_PENDING)
            ->exists();

        if ($hasPending) {
            return back()->with('error', 'You already have a pending payout request for this community.');
        }

        [$eligibleNow] = $this->ownerEligibility($community);

        if ($eligibleNow <= 0) {
            return back()->with('error', 'No eligible earnings yet. Payments must be at least 15 days old.');
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', "max:{$eligibleNow}"],
        ]);

        PayoutRequest::create([
            'user_id'         => $owner->id,
            'type'            => PayoutRequest::TYPE_OWNER,
            'community_id'    => $community->id,
            'amount'          => $validated['amount'],
            'eligible_amount' => $eligibleNow,
            'status'          => PayoutRequest::STATUS_PENDING,
        ]);

        return back()->with('success', 'Payout request submitted. The admin will review and process it shortly.');
    }

    /**
     * Affiliate submits a payout request.
     */
    public function storeAffiliate(Request $request, Affiliate $affiliate): RedirectResponse
    {
        abort_unless($affiliate->user_id === Auth::id(), 403);
        abort_unless(
            $affiliate->isActive(),
            422,
            'Your affiliate membership is suspended. Renew your subscription to re-enable payouts.'
        );
        abort_unless(
            in_array($affiliate->payout_method, ['gcash', 'maya']) && $affiliate->payout_details,
            422,
            'Please set your payout method before requesting a payout.'
        );

        $hasPending = PayoutRequest::where('affiliate_id', $affiliate->id)
            ->where('type', PayoutRequest::TYPE_AFFILIATE)
            ->whereIn('status', [PayoutRequest::STATUS_PENDING, PayoutRequest::STATUS_APPROVED])
            ->exists();

        if ($hasPending) {
            return back()->with('error', 'You already have an active payout request for this affiliate program.');
        }

        $eligibleNow = $this->affiliateEligibility($affiliate);

        if ($eligibleNow <= 0) {
            return back()->with('error', 'No eligible earnings yet. Commissions must be at least 15 days old.');
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', "max:{$eligibleNow}"],
        ]);

        PayoutRequest::create([
            'user_id'         => $affiliate->user_id,
            'type'            => PayoutRequest::TYPE_AFFILIATE,
            'community_id'    => $affiliate->community_id,
            'affiliate_id'    => $affiliate->id,
            'amount'          => $validated['amount'],
            'eligible_amount' => $eligibleNow,
            'status'          => PayoutRequest::STATUS_PENDING,
        ]);

        return back()->with('success', 'Payout request submitted. The admin will review and process it shortly.');
    }

    /**
     * Affiliate requests payout for ALL eligible affiliates at once.
     */
    public function storeAffiliateAll(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $affiliates = Affiliate::where('user_id', $user->id)
            ->where('status', Affiliate::STATUS_ACTIVE)
            ->whereIn('payout_method', ['gcash', 'maya'])
            ->whereNotNull('payout_details')
            ->get();

        if ($affiliates->isEmpty()) {
            return back()->with('error', 'No affiliates with a valid payout method set.');
        }

        $submitted = 0;
        foreach ($affiliates as $affiliate) {
            $hasPending = PayoutRequest::where('affiliate_id', $affiliate->id)
                ->where('type', PayoutRequest::TYPE_AFFILIATE)
                ->whereIn('status', [PayoutRequest::STATUS_PENDING, PayoutRequest::STATUS_APPROVED])
                ->exists();

            if ($hasPending) continue;

            $eligibleNow = self::affiliateEligibility($affiliate);
            if ($eligibleNow <= 0) continue;

            PayoutRequest::create([
                'user_id'         => $affiliate->user_id,
                'type'            => PayoutRequest::TYPE_AFFILIATE,
                'community_id'    => $affiliate->community_id,
                'affiliate_id'    => $affiliate->id,
                'amount'          => $eligibleNow,
                'eligible_amount' => $eligibleNow,
                'status'          => PayoutRequest::STATUS_PENDING,
            ]);

            $submitted++;
        }

        if ($submitted === 0) {
            return back()->with('error', 'No eligible affiliate earnings to request payout for.');
        }

        return back()->with('success', "Payout request submitted for {$submitted} affiliate program(s). The admin will review shortly.");
    }

    /**
     * Returns [eligibleNow, lockedAmount, nextEligibleDate] for an owner community.
     */
    public static function ownerEligibility(Community $community): array
    {
        $cutoff = now()->subDays(15);

        $eligibleGross = (float) Payment::where('community_id', $community->id)
            ->where('status', Payment::STATUS_PAID)
            ->where('paid_at', '<=', $cutoff)
            ->sum('amount');

        $lockedGross = (float) Payment::where('community_id', $community->id)
            ->where('status', Payment::STATUS_PAID)
            ->where('paid_at', '>', $cutoff)
            ->sum('amount');

        $affiliateCommission = (float) AffiliateConversion::whereHas(
            'affiliate', fn ($q) => $q->where('community_id', $community->id)
        )->sum('commission_amount');

        $platformFeeEligible = round($eligibleGross * 0.15, 2);
        $platformFeeLocked   = round($lockedGross * 0.15, 2);

        $eligibleEarned = round($eligibleGross - $platformFeeEligible - $affiliateCommission, 2);
        $lockedEarned   = round($lockedGross - $platformFeeLocked, 2);

        $alreadyPaid = (float) OwnerPayout::where('community_id', $community->id)
            ->where('status', '!=', 'failed')
            ->sum('amount');

        $pendingRequested = (float) PayoutRequest::where('community_id', $community->id)
            ->where('type', PayoutRequest::TYPE_OWNER)
            ->where('status', PayoutRequest::STATUS_PENDING)
            ->sum('amount');

        $eligibleNow = max(0, round($eligibleEarned - $alreadyPaid - $pendingRequested, 2));

        // Next eligible date = oldest locked payment + 15 days
        $oldestLocked = Payment::where('community_id', $community->id)
            ->where('status', Payment::STATUS_PAID)
            ->where('paid_at', '>', $cutoff)
            ->orderBy('paid_at')
            ->value('paid_at');

        $nextEligibleDate = $oldestLocked
            ? \Carbon\Carbon::parse($oldestLocked)->addDays(15)->toDateString()
            : null;

        return [$eligibleNow, max(0, $lockedEarned), $nextEligibleDate];
    }

    /**
     * Returns eligible affiliate commission amount (>15 days old, not yet in a pending request).
     */
    public static function affiliateEligibility(Affiliate $affiliate): float
    {
        $eligible = (float) AffiliateConversion::where('affiliate_id', $affiliate->id)
            ->where('status', AffiliateConversion::STATUS_PENDING)
            ->where('created_at', '<=', now()->subDays(15))
            ->sum('commission_amount');

        // Subtract both pending AND approved requests (not yet paid out / rejected)
        $inFlight = (float) PayoutRequest::where('affiliate_id', $affiliate->id)
            ->where('type', PayoutRequest::TYPE_AFFILIATE)
            ->whereIn('status', [PayoutRequest::STATUS_PENDING, PayoutRequest::STATUS_APPROVED])
            ->sum('amount');

        return max(0, round($eligible - $inFlight, 2));
    }
}
