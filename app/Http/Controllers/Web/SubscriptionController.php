<?php

namespace App\Http\Controllers\Web;

use App\Actions\Billing\StartSubscriptionCheckout;
use App\Http\Controllers\Controller;
use App\Models\Community;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubscriptionController extends Controller
{
    public function checkout(Request $request, Community $community, StartSubscriptionCheckout $action): mixed
    {
        if ($community->isPendingDeletion()) {
            return back()->with('error', 'This community is no longer accepting new members.');
        }

        $affiliateCode = $request->cookie('ref_code');

        $callbackUrl = GuestCheckoutController::buildCallbackUrl($request->user()->id, $community->slug);

        $result = $action->execute($request->user(), $community, $affiliateCode, successRedirectUrl: $callbackUrl);

        cookie()->forget('ref_code');

        return Inertia::location($result['checkout_url']);
    }
}
