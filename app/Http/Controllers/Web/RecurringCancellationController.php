<?php

namespace App\Http\Controllers\Web;

use App\Actions\Billing\CancelRecurringPlan;
use App\Actions\Billing\EnableAutoRenew;
use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;
use App\Models\CurzzoPurchase;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RecurringCancellationController extends Controller
{
    public function __construct(
        private readonly CancelRecurringPlan $cancelAction,
        private readonly EnableAutoRenew $enableAction,
    ) {}

    public function cancelSubscription(Request $request, Subscription $subscription): RedirectResponse
    {
        abort_unless($subscription->user_id === $request->user()->id, 403);
        abort_unless($subscription->isRecurring(), 400, 'This subscription is not recurring.');

        $this->cancelAction->execute($subscription);

        return back()->with('success', 'Auto-renewal cancelled. Your access continues until ' . $subscription->expires_at->format('M d, Y') . '.');
    }

    public function cancelCreatorPlan(Request $request): RedirectResponse
    {
        $user = $request->user();

        $creatorSub = $user->creatorSubscriptions()
            ->where('status', 'active')
            ->whereNotNull('xendit_plan_id')
            ->latest()
            ->firstOrFail();

        $this->cancelAction->execute($creatorSub);

        return back()->with('success', 'Auto-renewal cancelled. Your plan continues until ' . $creatorSub->expires_at->format('M d, Y') . '.');
    }

    // ─── Enable Auto-Renew ──────────────────────────────────────────────────

    public function enableSubscriptionAutoRenew(Request $request, Subscription $subscription): JsonResponse
    {
        abort_unless($subscription->user_id === $request->user()->id, 403);
        abort_unless($subscription->status === Subscription::STATUS_ACTIVE, 400, 'Subscription is not active.');
        abort_if($subscription->isRecurring(), 400, 'Auto-renew is already enabled.');

        $linkingUrl = $this->enableAction->executeForSubscription($subscription);

        return response()->json(['linking_url' => $linkingUrl]);
    }

    public function enableCreatorPlanAutoRenew(Request $request): JsonResponse
    {
        $user = $request->user();

        $creatorSub = $user->creatorSubscriptions()
            ->where('status', 'active')
            ->whereNull('xendit_plan_id')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest()
            ->firstOrFail();

        $linkingUrl = $this->enableAction->executeForCreatorPlan($creatorSub);

        return response()->json(['linking_url' => $linkingUrl]);
    }

    // ─── Cancel Recurring ─────────────────────────────────────────────────────

    public function cancelCourseEnrollment(Request $request, CourseEnrollment $courseEnrollment): RedirectResponse
    {
        abort_unless($courseEnrollment->user_id === $request->user()->id, 403);
        abort_unless($courseEnrollment->isRecurring(), 400, 'This enrollment is not recurring.');

        $this->cancelAction->execute($courseEnrollment);

        return back()->with('success', 'Auto-renewal cancelled. Your access continues until ' . $courseEnrollment->expires_at->format('M d, Y') . '.');
    }

    public function cancelCurzzoPurchase(Request $request, CurzzoPurchase $curzzoPurchase): RedirectResponse
    {
        abort_unless($curzzoPurchase->user_id === $request->user()->id, 403);
        abort_unless($curzzoPurchase->isRecurring(), 400, 'This purchase is not recurring.');

        $this->cancelAction->execute($curzzoPurchase);

        return back()->with('success', 'Auto-renewal cancelled. Your access continues until ' . $curzzoPurchase->expires_at->format('M d, Y') . '.');
    }
}
