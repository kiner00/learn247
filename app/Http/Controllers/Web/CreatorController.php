<?php

namespace App\Http\Controllers\Web;

use App\Actions\Billing\StartCreatorPlanCheckout;
use App\Actions\Billing\SwitchCreatorPlanCycle;
use App\Actions\Coupon\RedeemCoupon;
use App\Actions\Coupon\ValidateCreatorPlanCoupon;
use App\Http\Controllers\Controller;
use App\Models\CreatorSubscription;
use App\Queries\Creator\GetCreatorDashboard;
use App\Services\Analytics\CreatorAnalyticsService;
use App\Support\CreatorPlanPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class CreatorController extends Controller
{
    public function plan(): Response
    {
        $user = Auth::user();

        $activeSub = $user->creatorSubscriptions()
            ->where('status', CreatorSubscription::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest()
            ->first();

        return Inertia::render('Creator/Plan', [
            'basicPrice' => CreatorPlanPricing::priceFor(CreatorSubscription::PLAN_BASIC, CreatorSubscription::CYCLE_MONTHLY),
            'proPrice' => CreatorPlanPricing::priceFor(CreatorSubscription::PLAN_PRO, CreatorSubscription::CYCLE_MONTHLY),
            'basicAnnualPrice' => CreatorPlanPricing::priceFor(CreatorSubscription::PLAN_BASIC, CreatorSubscription::CYCLE_ANNUAL),
            'proAnnualPrice' => CreatorPlanPricing::priceFor(CreatorSubscription::PLAN_PRO, CreatorSubscription::CYCLE_ANNUAL),
            'currentPlan' => $user->creatorPlan(),
            'currentCycle' => $activeSub?->billing_cycle ?? CreatorSubscription::CYCLE_MONTHLY,
            'isAutoRenewing' => $activeSub?->isAutoRenewing() ?? false,
            'isRecurring' => $activeSub?->isRecurring() ?? false,
            'recurringStatus' => $activeSub?->recurring_status,
            'expiresAt' => $activeSub?->expires_at?->toDateTimeString(),
        ]);
    }

    public function planCheckout(Request $request, StartCreatorPlanCheckout $action)
    {
        $user = Auth::user();
        $data = $request->validate([
            'plan' => ['required', 'in:basic,pro'],
            'cycle' => ['sometimes', 'in:monthly,annual'],
            'coupon_code' => ['nullable', 'string', 'max:32'],
        ]);

        try {
            $result = $action->execute(
                $user,
                $data['plan'],
                $data['cycle'] ?? CreatorSubscription::CYCLE_MONTHLY,
                $data['coupon_code'] ?? null,
            );

            return response()->json(['checkout_url' => $result['checkout_url']]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('CreatorController@planCheckout failed', ['error' => $e->getMessage(), 'user_id' => $user->id]);

            return response()->json(['error' => 'Failed to start checkout.'], 500);
        }
    }

    public function validateCoupon(Request $request, ValidateCreatorPlanCoupon $action): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'plan' => ['required', 'in:basic,pro'],
            'cycle' => ['required', 'in:monthly,annual'],
        ]);

        $result = $action->execute(Auth::user(), $data['code'], $data['plan'], $data['cycle']);

        return response()->json([
            'code' => $result['coupon']->code,
            'plan' => $result['plan'],
            'cycle' => $result['cycle'],
            'original_price' => $result['original_price'],
            'discounted_price' => $result['discounted_price'],
            'discount_percent' => $result['discount_percent'],
            'savings' => $result['savings'],
        ]);
    }

    public function switchCycle(Request $request, SwitchCreatorPlanCycle $action): JsonResponse
    {
        $user = Auth::user();
        $cycle = $request->validate(['cycle' => ['required', 'in:monthly,annual']])['cycle'];

        $creatorSub = $user->creatorSubscriptions()
            ->where('status', CreatorSubscription::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest()
            ->firstOrFail();

        $result = $action->execute($creatorSub, $cycle);

        return response()->json([
            'billing_cycle' => $result['creator_subscription']->billing_cycle,
            'linking_url' => $result['linking_url'],
            'message' => 'Billing cycle will change at your next renewal.',
        ]);
    }

    public function redeemCoupon(Request $request, RedeemCoupon $action)
    {
        $code = $request->validate(['code' => 'required|string|max:32'])['code'];

        try {
            $sub = $action->execute(Auth::user(), $code);

            return back()->with('success', "Coupon redeemed! You now have the {$sub->plan} plan until {$sub->expires_at->format('M d, Y')}.");
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }
    }

    public function dashboard(GetCreatorDashboard $query, CreatorAnalyticsService $analyticsService): Response
    {
        $user = Auth::user();

        try {
            $data = $query->execute($user);
            $currentPlan = $user->creatorPlan();
            $analytics = in_array($currentPlan, ['basic', 'pro']) ? $analyticsService->build($user->id) : null;

            return Inertia::render('Creator/Dashboard', array_merge($data, [
                'analytics' => $analytics,
                'currentPlan' => $currentPlan,
            ]));
        } catch (\Throwable $e) {
            Log::error('CreatorController@dashboard failed', ['error' => $e->getMessage(), 'user_id' => $user->id]);

            return Inertia::render('Creator/Dashboard', [
                'communities' => [],
                'requestHistory' => [],
                'error' => 'Failed to load dashboard data.',
            ]);
        }
    }
}
