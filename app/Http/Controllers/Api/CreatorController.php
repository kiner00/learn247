<?php

namespace App\Http\Controllers\Api;

use App\Actions\Billing\CancelRecurringPlan;
use App\Actions\Billing\EnableAutoRenew;
use App\Actions\Billing\StartCreatorPlanCheckout;
use App\Actions\Billing\SwitchCreatorPlanCycle;
use App\Http\Controllers\Controller;
use App\Models\CreatorSubscription;
use App\Queries\Creator\GetCreatorDashboard;
use App\Support\CreatorPlanPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CreatorController extends Controller
{
    public function dashboard(Request $request, GetCreatorDashboard $query): JsonResponse
    {
        $user = $request->user();

        try {
            $data = $query->execute($user);

            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error('Api\CreatorController@dashboard failed', ['error' => $e->getMessage(), 'user_id' => $user->id]);

            return response()->json(['message' => 'Failed to load dashboard data.'], 500);
        }
    }

    public function plan(Request $request): JsonResponse
    {
        $user = $request->user();

        $activeSub = $user->creatorSubscriptions()
            ->where('status', CreatorSubscription::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest()
            ->first();

        return response()->json([
            'pricing' => [
                'basic_monthly' => CreatorPlanPricing::priceFor(CreatorSubscription::PLAN_BASIC, CreatorSubscription::CYCLE_MONTHLY),
                'pro_monthly' => CreatorPlanPricing::priceFor(CreatorSubscription::PLAN_PRO, CreatorSubscription::CYCLE_MONTHLY),
                'basic_annual' => CreatorPlanPricing::priceFor(CreatorSubscription::PLAN_BASIC, CreatorSubscription::CYCLE_ANNUAL),
                'pro_annual' => CreatorPlanPricing::priceFor(CreatorSubscription::PLAN_PRO, CreatorSubscription::CYCLE_ANNUAL),
                'currency' => 'PHP',
            ],
            'current_plan' => $user->creatorPlan(),
            'current_cycle' => $activeSub?->billing_cycle ?? CreatorSubscription::CYCLE_MONTHLY,
            'is_auto_renewing' => $activeSub?->isAutoRenewing() ?? false,
            'is_recurring' => $activeSub?->isRecurring() ?? false,
            'recurring_status' => $activeSub?->recurring_status,
            'expires_at' => $activeSub?->expires_at?->toIso8601String(),
        ]);
    }

    public function checkout(Request $request, StartCreatorPlanCheckout $action): JsonResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'in:basic,pro'],
            'cycle' => ['sometimes', 'in:monthly,annual'],
        ]);

        try {
            $result = $action->execute(
                $request->user(),
                $data['plan'],
                $data['cycle'] ?? CreatorSubscription::CYCLE_MONTHLY,
            );

            return response()->json([
                'checkout_url' => $result['checkout_url'],
                'subscription_id' => $result['creator_subscription']->id,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Api\CreatorController@checkout failed', ['error' => $e->getMessage(), 'user_id' => $request->user()->id]);

            return response()->json(['message' => 'Failed to start checkout.'], 500);
        }
    }

    public function switchCycle(Request $request, SwitchCreatorPlanCycle $action): JsonResponse
    {
        $cycle = $request->validate(['cycle' => ['required', 'in:monthly,annual']])['cycle'];

        $creatorSub = $request->user()->creatorSubscriptions()
            ->where('status', CreatorSubscription::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest()
            ->first();

        if (! $creatorSub) {
            return response()->json(['message' => 'No active plan found.'], 404);
        }

        try {
            $result = $action->execute($creatorSub, $cycle);

            return response()->json([
                'billing_cycle' => $result['creator_subscription']->billing_cycle,
                'linking_url' => $result['linking_url'],
                'message' => 'Billing cycle will change at your next renewal.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Api\CreatorController@switchCycle failed', ['error' => $e->getMessage(), 'user_id' => $request->user()->id]);

            return response()->json(['message' => 'Failed to switch billing cycle.'], 500);
        }
    }

    public function enableAutoRenew(Request $request, EnableAutoRenew $action): JsonResponse
    {
        $creatorSub = $request->user()->creatorSubscriptions()
            ->where('status', CreatorSubscription::STATUS_ACTIVE)
            ->whereNull('xendit_plan_id')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest()
            ->first();

        if (! $creatorSub) {
            return response()->json(['message' => 'No active plan eligible for auto-renew.'], 404);
        }

        try {
            $linkingUrl = $action->executeForCreatorPlan($creatorSub);

            return response()->json(['linking_url' => $linkingUrl]);
        } catch (\Throwable $e) {
            Log::error('Api\CreatorController@enableAutoRenew failed', ['error' => $e->getMessage(), 'user_id' => $request->user()->id]);

            return response()->json(['message' => 'Failed to enable auto-renew.'], 500);
        }
    }

    public function cancelAutoRenew(Request $request, CancelRecurringPlan $action): JsonResponse
    {
        $creatorSub = $request->user()->creatorSubscriptions()
            ->where('status', CreatorSubscription::STATUS_ACTIVE)
            ->whereNotNull('xendit_plan_id')
            ->latest()
            ->first();

        if (! $creatorSub) {
            return response()->json(['message' => 'No recurring plan found.'], 404);
        }

        try {
            $action->execute($creatorSub);

            return response()->json([
                'message' => 'Auto-renewal cancelled. Your plan continues until '.$creatorSub->expires_at?->toIso8601String().'.',
                'expires_at' => $creatorSub->expires_at?->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Api\CreatorController@cancelAutoRenew failed', ['error' => $e->getMessage(), 'user_id' => $request->user()->id]);

            return response()->json(['message' => 'Failed to cancel auto-renew.'], 500);
        }
    }
}
