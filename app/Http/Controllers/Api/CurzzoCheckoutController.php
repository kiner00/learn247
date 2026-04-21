<?php

namespace App\Http\Controllers\Api;

use App\Actions\Billing\StartCurzzoCheckout;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Curzzo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurzzoCheckoutController extends Controller
{
    public function checkout(Request $request, Community $community, Curzzo $curzzo, StartCurzzoCheckout $action): JsonResponse
    {
        abort_unless($curzzo->community_id === $community->id && $curzzo->is_active, 404);

        $request->validate([
            'affiliate_code' => ['nullable', 'string', 'max:100'],
            'success_redirect_url' => ['nullable', 'url'],
        ]);

        $result = $action->execute(
            $request->user(),
            $curzzo,
            $request->input('affiliate_code'),
            successRedirectUrl: $request->input('success_redirect_url'),
        );

        return response()->json([
            'purchase_id' => $result['purchase']->id,
            'checkout_url' => $result['checkout_url'],
        ]);
    }
}
