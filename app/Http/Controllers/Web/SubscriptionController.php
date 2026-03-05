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
        $affiliateCode = $request->cookie('ref_code');

        $result = $action->execute($request->user(), $community, $affiliateCode);

        // Clear the ref cookie after checkout starts (one-use attribution)
        cookie()->forget('ref_code');

        // Inertia::location triggers a full-page browser redirect (works for external URLs)
        return Inertia::location($result['checkout_url']);
    }
}
