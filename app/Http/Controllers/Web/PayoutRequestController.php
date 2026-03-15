<?php

namespace App\Http\Controllers\Web;

use App\Actions\Payout\RequestAffiliatePayout;
use App\Actions\Payout\RequestOwnerPayout;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\Community;
use App\Models\PayoutRequest;
use App\Queries\Payout\CalculateEligibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PayoutRequestController extends Controller
{
    public function storeOwner(Request $request, Community $community, RequestOwnerPayout $action): RedirectResponse
    {
        abort_unless($community->owner_id === Auth::id(), 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        $result = $action->execute(Auth::user(), $community, (float) $validated['amount']);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function storeAffiliate(Request $request, Affiliate $affiliate, RequestAffiliatePayout $action): RedirectResponse
    {
        abort_unless($affiliate->user_id === Auth::id(), 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        $result = $action->execute($affiliate, (float) $validated['amount']);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function storeAffiliateAll(Request $request, CalculateEligibility $eligibility): RedirectResponse
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

            $eligibleNow = $eligibility->forAffiliate($affiliate);
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
     * @deprecated Use CalculateEligibility query instead. Kept for backward compat with AdminController.
     */
    public static function ownerEligibility(Community $community): array
    {
        return app(CalculateEligibility::class)->forOwner($community);
    }

    /**
     * @deprecated Use CalculateEligibility query instead. Kept for backward compat.
     */
    public static function affiliateEligibility(Affiliate $affiliate): float
    {
        return app(CalculateEligibility::class)->forAffiliate($affiliate);
    }
}
