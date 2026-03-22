<?php

namespace App\Http\Controllers\Web;

use App\Actions\Payout\RequestAffiliatePayout;
use App\Actions\Payout\RequestAllAffiliatePayouts;
use App\Actions\Payout\RequestOwnerPayout;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\Community;
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

    public function storeAffiliateAll(RequestAllAffiliatePayouts $action): RedirectResponse
    {
        $result = $action->execute(Auth::user());

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
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
