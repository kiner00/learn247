<?php

namespace App\Services\Analytics;

use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\LessonCompletion;
use App\Models\Payment;
use App\Models\Subscription;
use App\Queries\Payout\CalculateEligibility;
use App\Models\PayoutRequest;
use App\Models\Quiz;
use App\Models\QuizAttempt;

/**
 * Aggregates revenue, subscriber, and course stats for the community analytics
 * page. Extracted from CommunityController::analytics() so the API can return
 * the same payload without duplication.
 */
class CommunityAnalyticsService
{
    public function __construct(private CalculateEligibility $eligibility) {}

    public function build(Community $community): array
    {
        // ── Subscription counts ───────────────────────────────────────────────
        $activeCount = Subscription::where('community_id', $community->id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereHas('payments', fn ($q) => $q->where('status', Payment::STATUS_PAID))
            ->count();

        $monthlyRevenue = $activeCount * (float) $community->price;
        $totalMembers   = $community->members()->count();

        // ── Subscriber list ───────────────────────────────────────────────────
        $subscribers = Subscription::where('community_id', $community->id)
            ->with(['user', 'payments' => fn ($q) => $q->where('status', Payment::STATUS_PAID)->orderByDesc('paid_at')->limit(1)])
            ->latest()
            ->get()
            ->map(fn ($s) => [
                'id'          => $s->id,
                'user'        => ['name' => $s->user?->name, 'email' => $s->user?->email],
                'status'      => $s->status,
                'expires_at'  => $s->expires_at?->toDateString(),
                'created_at'  => $s->created_at?->toDateString(),
                'amount_paid' => $s->payments->first()?->amount !== null
                    ? (float) $s->payments->first()->amount
                    : null,
            ]);

        // ── Revenue breakdown ─────────────────────────────────────────────────
        $grossRevenue = (float) Payment::where('community_id', $community->id)
            ->where('status', Payment::STATUS_PAID)
            ->sum('amount');

        $conversionBase       = AffiliateConversion::whereHas('affiliate', fn ($q) => $q->where('community_id', $community->id));
        $affiliateGross       = (float) (clone $conversionBase)->sum('sale_amount');
        $affiliatePlatformFee = (float) (clone $conversionBase)->sum('platform_fee');
        $affiliateCommission  = (float) (clone $conversionBase)->sum('commission_amount');
        $affiliateCreator     = (float) (clone $conversionBase)->sum('creator_amount');
        $affiliatePaid        = (float) (clone $conversionBase)->where('status', AffiliateConversion::STATUS_PAID)->sum('commission_amount');
        $affiliatePending     = (float) (clone $conversionBase)->where('status', AffiliateConversion::STATUS_PENDING)->sum('commission_amount');

        $nonAffiliateGross       = round($grossRevenue - $affiliateGross, 2);
        $nonAffiliatePlatformFee = round($nonAffiliateGross * $community->platformFeeRate(), 2);
        $nonAffiliateCreator     = round($nonAffiliateGross - $nonAffiliatePlatformFee, 2);
        $totalPlatformFee        = round($affiliatePlatformFee + $nonAffiliatePlatformFee, 2);
        $totalCreatorNet         = round($affiliateCreator + $nonAffiliateCreator, 2);

        // ── Course stats ──────────────────────────────────────────────────────
        $courses     = $community->courses()->with('modules.lessons')->get();
        $courseStats = $courses->map(function ($course) {
            $paidModules      = $course->modules->where('is_free', false);
            $lessonIds        = $paidModules->flatMap(fn ($m) => $m->lessons->pluck('id'));
            $totalLessons     = $lessonIds->count();
            $completedMembers = $totalLessons > 0
                ? LessonCompletion::whereIn('lesson_id', $lessonIds)
                    ->selectRaw('user_id, count(*) as cnt')
                    ->groupBy('user_id')
                    ->havingRaw('cnt >= ?', [$totalLessons])
                    ->count()
                : 0;
            $totalCompletions = LessonCompletion::whereIn('lesson_id', $lessonIds)->count();
            $quizIds          = Quiz::whereIn('lesson_id', $lessonIds)->pluck('id');
            $quizAttempts     = QuizAttempt::whereIn('quiz_id', $quizIds)->count();
            $quizPasses       = QuizAttempt::whereIn('quiz_id', $quizIds)->where('passed', true)->count();

            return [
                'id'                => $course->id,
                'title'             => $course->title,
                'total_lessons'     => $totalLessons,
                'total_completions' => $totalCompletions,
                'completed_members' => $completedMembers,
                'quiz_attempts'     => $quizAttempts,
                'quiz_passes'       => $quizPasses,
                'quiz_pass_rate'    => $quizAttempts > 0 ? round($quizPasses / $quizAttempts * 100) : null,
            ];
        });

        // ── Payout eligibility ────────────────────────────────────────────────
        [$eligibleNow, $lockedAmount, $nextEligibleDate] = $this->eligibility->forOwner($community);

        $pendingPayoutRequest = PayoutRequest::where('community_id', $community->id)
            ->where('type', PayoutRequest::TYPE_OWNER)
            ->where('status', PayoutRequest::STATUS_PENDING)
            ->latest()
            ->first();

        $payoutHistory = \App\Models\OwnerPayout::where('community_id', $community->id)
            ->latest('paid_at')
            ->get()
            ->map(fn ($p) => [
                'amount'    => $p->amount,
                'status'    => $p->status,
                'paid_at'   => $p->paid_at?->toDateString(),
                'reference' => $p->xendit_reference,
            ]);

        return [
            'stats' => [
                'monthly_revenue'      => $monthlyRevenue,
                'active_subscriptions' => $activeCount,
                'total_members'        => $totalMembers,
                'free_members'         => $totalMembers - $activeCount,
            ],
            'revenue' => [
                'gross'                          => $grossRevenue,
                'platform_fee'                   => $totalPlatformFee,
                'platform_fee_rate'              => $community->platformFeeRate(),
                'affiliate_commission_earned'    => $affiliateCommission,
                'affiliate_commission_paid'      => $affiliatePaid,
                'affiliate_commission_pending'   => $affiliatePending,
                'creator_net'                    => $totalCreatorNet,
                'has_affiliate_data'             => $affiliateGross > 0,
            ],
            'payout' => [
                'eligible_now'      => $eligibleNow,
                'locked_amount'     => $lockedAmount,
                'next_eligible_date'=> $nextEligibleDate,
                'pending_request'   => $pendingPayoutRequest
                    ? ['amount' => $pendingPayoutRequest->amount, 'created_at' => $pendingPayoutRequest->created_at->toDateString()]
                    : null,
            ],
            'payout_history' => $payoutHistory,
            'subscribers'    => $subscribers,
            'course_stats'   => $courseStats->values(),
        ];
    }
}
