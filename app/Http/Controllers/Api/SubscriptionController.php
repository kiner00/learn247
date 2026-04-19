<?php

namespace App\Http\Controllers\Api;

use App\Actions\Billing\StartSubscriptionCheckout;
use App\Http\Controllers\Controller;
use App\Models\Community;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function checkout(Request $request, Community $community, StartSubscriptionCheckout $action): JsonResponse
    {
        $affiliateCode = $request->cookie('ref_code');

        $result = $action->execute($request->user(), $community, $affiliateCode);

        return response()->json([
            'checkout_url' => $result['checkout_url'],
            'subscription_id' => $result['subscription']->id,
        ]);
    }
}
