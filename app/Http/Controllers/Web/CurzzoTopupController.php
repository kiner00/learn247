<?php

namespace App\Http\Controllers\Web;

use App\Actions\Billing\StartCurzzoTopupCheckout;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Services\Community\CurzzoLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CurzzoTopupController extends Controller
{
    public function packs(Community $community, CurzzoLimitService $limits): JsonResponse
    {
        return response()->json([
            'packs' => $limits->getPacks($community),
        ]);
    }

    public function checkout(Request $request, Community $community, StartCurzzoTopupCheckout $action, CurzzoLimitService $limits): mixed
    {
        $request->validate([
            'pack_index' => ['required', 'integer', 'min:0'],
        ]);

        $packs = $limits->getPacks($community);
        $index = $request->pack_index;

        if (! isset($packs[$index])) {
            return back()->withErrors(['pack_index' => 'Invalid pack selected.']);
        }

        $pack = $packs[$index];

        $callbackUrl = GuestCheckoutController::buildCallbackUrl($request->user()->id, $community->slug);

        $result = $action->execute($request->user(), $community, $pack, successRedirectUrl: $callbackUrl);

        return Inertia::location($result['checkout_url']);
    }
}
