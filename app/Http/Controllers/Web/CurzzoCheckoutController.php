<?php

namespace App\Http\Controllers\Web;

use App\Actions\Billing\StartCurzzoCheckout;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Curzzo;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CurzzoCheckoutController extends Controller
{
    public function checkout(Request $request, Community $community, Curzzo $curzzo, StartCurzzoCheckout $action): mixed
    {
        abort_unless($curzzo->community_id === $community->id && $curzzo->is_active, 404);

        $affiliateCode = $request->cookie('ref_code');

        $callbackUrl = GuestCheckoutController::buildCallbackUrl($request->user()->id, $community->slug);

        $result = $action->execute($request->user(), $curzzo, $affiliateCode, successRedirectUrl: $callbackUrl);

        cookie()->forget('ref_code');

        return Inertia::location($result['checkout_url']);
    }
}
