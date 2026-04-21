<?php

namespace App\Http\Controllers\Api;

use App\Actions\Billing\CancelRecurringPlan;
use App\Actions\Billing\CheckSubscriptionStatus;
use App\Actions\Billing\StartSubscriptionCheckout;
use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionStatusResource;
use App\Models\Community;
use App\Models\Subscription;
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

    public function checkStatus(Request $request, Subscription $subscription, CheckSubscriptionStatus $action): SubscriptionStatusResource
    {
        abort_unless($subscription->user_id === $request->user()->id, 403);

        return new SubscriptionStatusResource($action->execute($subscription));
    }

    public function cancelRecurring(Request $request, Subscription $subscription, CancelRecurringPlan $action): SubscriptionStatusResource
    {
        abort_unless($subscription->user_id === $request->user()->id, 403);
        abort_unless($subscription->isRecurring(), 400, 'This subscription is not recurring.');

        $action->execute($subscription);

        return new SubscriptionStatusResource($subscription->fresh());
    }
}
