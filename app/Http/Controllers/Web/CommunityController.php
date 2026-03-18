<?php

namespace App\Http\Controllers\Web;

use App\Actions\Community\CreateCommunity;
use App\Actions\Community\JoinCommunity;
use App\Actions\Community\ManageGallery;
use App\Actions\Community\SendAnnouncement;
use App\Actions\Community\UpdateCommunity;
use App\Actions\Community\UpdateLevelPerks;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCommunityRequest;
use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\CommunityLevelPerk;
use App\Models\CommunityMember;
use App\Models\LessonCompletion;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\Post;
use App\Models\QuizAttempt;
use App\Models\Subscription;
use App\Queries\Community\GetLeaderboard;
use App\Queries\Community\ListCommunities;
use App\Queries\Feed\GetCommunityFeed;
use App\Queries\Payout\CalculateEligibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CommunityController extends Controller
{
    public function index(Request $request, ListCommunities $query): Response
    {
        $search   = $request->string('search')->trim()->toString();
        $category = $request->string('category')->trim()->toString();
        $sort     = $request->input('sort', 'latest');

        return Inertia::render('Communities/Index', [
            'communities' => $query->execute($search, $category, $sort),
            'filters'     => ['search' => $search, 'category' => $category ?: 'All', 'sort' => $sort],
        ]);
    }

    public function store(CreateCommunityRequest $request, CreateCommunity $action): RedirectResponse
    {
        $community = $action->execute(
            $request->user(),
            $request->validated(),
            $request->file('avatar'),
            $request->file('cover_image'),
        );

        return redirect()->route('communities.show', $community->slug)
            ->with('success', 'Community created!');
    }

    public function show(Community $community, GetLeaderboard $leaderboard, GetCommunityFeed $feed): Response
    {
        $this->authorize('view', $community);

        $userId = auth()->id();

        $feed->forShow($community, $userId);

        $membership = $userId ? $community->members()->where('user_id', $userId)->first() : null;
        $affiliate  = $userId ? $community->affiliates()->where('user_id', $userId)->first() : null;

        if (! $affiliate && $userId) {
            $isActiveSubscriber = Subscription::where('user_id', $userId)
                ->where('community_id', $community->id)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->where('expires_at', '>', now())
                ->exists();

            if ($isActiveSubscriber) {
                do { $code = Str::random(12); } while (Affiliate::where('code', $code)->exists());
                $affiliate = Affiliate::create([
                    'community_id' => $community->id,
                    'user_id'      => $userId,
                    'code'         => $code,
                    'status'       => Affiliate::STATUS_ACTIVE,
                ]);
            }
        }

        $adminCount = $community->members()->where('role', CommunityMember::ROLE_ADMIN)->count();
        $topMembers = $leaderboard->topMembers($community);

        $checklist = null;
        if ($userId && $community->owner_id === $userId) {
            $hasPost    = Post::where('community_id', $community->id)->exists();
            $courseCount = $community->courses()->count();
            $checklist = [
                ['key' => 'cover',       'label' => 'Upload a banner image',       'done' => (bool) $community->cover_image],
                ['key' => 'description', 'label' => 'Add a community description', 'done' => (bool) trim($community->description ?? '')],
                ['key' => 'post',        'label' => 'Write your first post',       'done' => $hasPost],
                ['key' => 'course',      'label' => 'Create a course',             'done' => $courseCount > 0],
                ['key' => 'affiliate',   'label' => 'Set affiliate commission',    'done' => (bool) $community->affiliate_commission_rate],
            ];
        }

        return Inertia::render('Communities/Show', compact(
            'community', 'membership', 'affiliate', 'adminCount', 'topMembers', 'checklist'
        ));
    }

    public function members(Community $community, Request $request): Response
    {
        $query = $community->members()->with('user:id,name,username,bio');

        if ($request->filter === 'admin') {
            $query->where('role', 'admin');
        }

        $members    = $query->orderByRaw("CASE role WHEN 'admin' THEN 0 WHEN 'moderator' THEN 1 ELSE 2 END")->paginate(20)->withQueryString();
        $totalCount = $community->members()->count();
        $adminCount = $community->members()->where('role', 'admin')->count();
        $affiliate  = auth()->id() ? $community->affiliates()->where('user_id', auth()->id())->first() : null;

        return Inertia::render('Communities/Members', compact('community', 'members', 'totalCount', 'adminCount', 'affiliate'));
    }

    public function settings(Community $community): Response
    {
        $this->authorize('update', $community);

        $moduleCount = $community->courses()
            ->withCount(['modules' => fn ($q) => $q->where('is_free', false)])
            ->get()->sum('modules_count');
        $owner       = $community->owner;

        $pricingGate = [
            'module_count'       => $moduleCount,
            'has_banner'         => (bool) $community->cover_image,
            'has_description'    => (bool) ($community->description && strlen(trim($community->description)) > 0),
            'profile_complete'   => (bool) ($owner && $owner->name && $owner->bio && $owner->avatar),
            'can_enable_pricing' => false,
        ];
        $pricingGate['can_enable_pricing'] = $moduleCount >= 5
            && $pricingGate['has_banner'] && $pricingGate['has_description'] && $pricingGate['profile_complete'];

        $levelPerks = CommunityLevelPerk::where('community_id', $community->id)->pluck('description', 'level')->toArray();

        return Inertia::render('Communities/Settings', compact('community', 'pricingGate', 'levelPerks'));
    }

    public function update(Request $request, Community $community, UpdateCommunity $action): RedirectResponse
    {
        $this->authorize('update', $community);

        $data = $request->validate([
            'name'                     => ['required', 'string', 'max:255'],
            'description'              => ['nullable', 'string', 'max:2000'],
            'category'                 => ['nullable', 'string', 'in:Tech,Business,Design,Health,Education,Finance,Other'],
            'avatar'                   => ['nullable', 'image', 'max:10240'],
            'cover_image'              => ['nullable', 'image', 'max:10240'],
            'price'                    => ['nullable', 'numeric', 'min:0'],
            'currency'                 => ['nullable', 'string', 'in:PHP,USD'],
            'billing_type'             => ['nullable', 'string', 'in:monthly,one_time'],
            'is_private'               => ['boolean'],
            'affiliate_commission_rate' => ['nullable', 'integer', 'min:0', 'max:85'],
            'facebook_pixel_id'         => ['nullable', 'string', 'max:30', 'regex:/^\d+$/'],
            'tiktok_pixel_id'           => ['nullable', 'string', 'max:30', 'regex:/^[A-Z0-9]+$/i'],
            'google_analytics_id'       => ['nullable', 'string', 'max:20', 'regex:/^G-[A-Z0-9]+$/i'],
        ]);

        $action->execute($community, $data, $request->file('avatar'), $request->file('cover_image'));

        return back()->with('success', 'Community updated.');
    }

    public function addGalleryImage(Request $request, Community $community, ManageGallery $action): RedirectResponse
    {
        $this->authorize('update', $community);
        $request->validate(['image' => ['required', 'image', 'max:10240']]);

        $action->addImage($community, $request->file('image'));

        return back()->with('success', 'Image added!');
    }

    public function removeGalleryImage(Request $request, Community $community, int $index, ManageGallery $action): RedirectResponse
    {
        $this->authorize('update', $community);

        $action->removeImage($community, $index);

        return back()->with('success', 'Image removed!');
    }

    public function updateLevelPerks(Request $request, Community $community, UpdateLevelPerks $action): RedirectResponse
    {
        $this->authorize('update', $community);

        $data = $request->validate([
            'perks'   => ['nullable', 'array'],
            'perks.*' => ['nullable', 'string', 'max:255'],
        ]);

        $action->execute($community, $data['perks'] ?? []);

        return back()->with('success', 'Level perks saved.');
    }

    public function destroy(Community $community): RedirectResponse
    {
        $this->authorize('delete', $community);
        $community->delete();

        return redirect()->route('communities.index')->with('success', 'Community deleted.');
    }

    public function analytics(Community $community, CalculateEligibility $eligibility): Response
    {
        $this->authorize('viewAnalytics', $community);

        $activeCount = Subscription::where('community_id', $community->id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereHas('payments', fn ($q) => $q->where('status', Payment::STATUS_PAID))
            ->count();

        $monthlyRevenue = $activeCount * (float) $community->price;
        $totalMembers   = $community->members()->count();

        $subscribers = Subscription::where('community_id', $community->id)
            ->with(['user', 'payments' => fn ($q) => $q->where('status', Payment::STATUS_PAID)->orderByDesc('paid_at')->limit(1)])
            ->latest()->get()
            ->map(fn ($s) => [
                'id'          => $s->id,
                'user'        => ['name' => $s->user?->name, 'email' => $s->user?->email],
                'status'      => $s->status,
                'expires_at'  => $s->expires_at?->toDateString(),
                'created_at'  => $s->created_at?->toDateString(),
                'amount_paid' => $s->payments->first()?->amount !== null ? (float) $s->payments->first()->amount : null,
            ]);

        $grossRevenue = (float) Payment::where('community_id', $community->id)->where('status', Payment::STATUS_PAID)->sum('amount');

        $conversionBase = AffiliateConversion::whereHas('affiliate', fn ($q) => $q->where('community_id', $community->id));
        $affiliateGross       = (float) (clone $conversionBase)->sum('sale_amount');
        $affiliatePlatformFee = (float) (clone $conversionBase)->sum('platform_fee');
        $affiliateCommission  = (float) (clone $conversionBase)->sum('commission_amount');
        $affiliateCreator     = (float) (clone $conversionBase)->sum('creator_amount');
        $affiliatePaid        = (float) (clone $conversionBase)->where('status', AffiliateConversion::STATUS_PAID)->sum('commission_amount');
        $affiliatePending     = (float) (clone $conversionBase)->where('status', AffiliateConversion::STATUS_PENDING)->sum('commission_amount');

        $nonAffiliateGross       = round($grossRevenue - $affiliateGross, 2);
        $nonAffiliatePlatformFee = round($nonAffiliateGross * 0.15, 2);
        $nonAffiliateCreator     = round($nonAffiliateGross - $nonAffiliatePlatformFee, 2);
        $totalPlatformFee = round($affiliatePlatformFee + $nonAffiliatePlatformFee, 2);
        $totalCreatorNet  = round($affiliateCreator + $nonAffiliateCreator, 2);

        $courses = $community->courses()->with('modules.lessons')->get();
        $courseStats = $courses->map(function ($course) {
            $paidModules  = $course->modules->where('is_free', false);
            $lessonIds    = $paidModules->flatMap(fn ($m) => $m->lessons->pluck('id'));
            $totalLessons = $lessonIds->count();
            $completedMembers = $totalLessons > 0
                ? LessonCompletion::whereIn('lesson_id', $lessonIds)->selectRaw('user_id, count(*) as cnt')->groupBy('user_id')->havingRaw('cnt >= ?', [$totalLessons])->count()
                : 0;
            $totalCompletions = LessonCompletion::whereIn('lesson_id', $lessonIds)->count();
            $quizIds      = \App\Models\Quiz::whereIn('lesson_id', $lessonIds)->pluck('id');
            $quizAttempts = QuizAttempt::whereIn('quiz_id', $quizIds)->count();
            $quizPasses   = QuizAttempt::whereIn('quiz_id', $quizIds)->where('passed', true)->count();

            return [
                'id' => $course->id, 'title' => $course->title, 'total_lessons' => $totalLessons,
                'total_completions' => $totalCompletions, 'completed_members' => $completedMembers,
                'quiz_attempts' => $quizAttempts, 'quiz_passes' => $quizPasses,
                'quiz_pass_rate' => $quizAttempts > 0 ? round($quizPasses / $quizAttempts * 100) : null,
            ];
        });

        [$eligibleNow, $lockedAmount, $nextEligibleDate] = $eligibility->forOwner($community);

        $pendingPayoutRequest = PayoutRequest::where('community_id', $community->id)
            ->where('type', PayoutRequest::TYPE_OWNER)->where('status', PayoutRequest::STATUS_PENDING)->latest()->first();

        $payoutHistory = \App\Models\OwnerPayout::where('community_id', $community->id)->latest('paid_at')->get()
            ->map(fn ($p) => ['amount' => $p->amount, 'status' => $p->status, 'paid_at' => $p->paid_at?->toDateString(), 'reference' => $p->xendit_reference]);

        return Inertia::render('Communities/Analytics', [
            'community' => $community,
            'stats' => ['monthly_revenue' => $monthlyRevenue, 'active_subscriptions' => $activeCount, 'total_members' => $totalMembers, 'free_members' => $totalMembers - $activeCount],
            'revenue' => ['gross' => $grossRevenue, 'platform_fee' => $totalPlatformFee, 'affiliate_commission_earned' => $affiliateCommission, 'affiliate_commission_paid' => $affiliatePaid, 'affiliate_commission_pending' => $affiliatePending, 'creator_net' => $totalCreatorNet, 'has_affiliate_data' => $affiliateGross > 0],
            'payout' => ['eligible_now' => $eligibleNow, 'locked_amount' => $lockedAmount, 'next_eligible_date' => $nextEligibleDate, 'pending_request' => $pendingPayoutRequest ? ['amount' => $pendingPayoutRequest->amount, 'created_at' => $pendingPayoutRequest->created_at->toDateString()] : null],
            'payout_history' => $payoutHistory, 'subscribers' => $subscribers, 'course_stats' => $courseStats->values(),
        ]);
    }

    public function join(Request $request, Community $community, JoinCommunity $action): RedirectResponse
    {
        $action->execute($request->user(), $community);

        return back()->with('success', 'You have joined the community!');
    }

    public function announce(Request $request, Community $community, SendAnnouncement $action): RedirectResponse
    {
        $this->authorize('update', $community);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $count = $action->execute($request->user(), $community, $data['subject'], $data['message']);

        return back()->with('success', "Announcement sent to {$count} members.");
    }

    public function about(Request $request, Community $community): Response
    {
        $community->load('owner')->loadCount('members');
        $affiliate = auth()->id() ? $community->affiliates()->where('user_id', auth()->id())->first() : null;

        $recentMembers = $community->members()->with('user:id,name,avatar')->latest()->take(8)->get()
            ->map(fn ($m) => ['name' => $m->user?->name, 'avatar' => $m->user?->avatar])
            ->filter(fn ($m) => $m['name'])->values();

        $invitedBy = null;
        $refCode   = $request->cookie('ref_code');
        if ($refCode) {
            $refAffiliate = Affiliate::where('code', $refCode)->where('community_id', $community->id)
                ->where('status', Affiliate::STATUS_ACTIVE)->with('user:id,name,avatar')->first();
            if ($refAffiliate) {
                $invitedBy = [
                    'name'                => $refAffiliate->user->name,
                    'avatar'              => $refAffiliate->user->avatar,
                    'code'                => $refCode,
                    'facebook_pixel_id'   => $refAffiliate->facebook_pixel_id,
                    'tiktok_pixel_id'     => $refAffiliate->tiktok_pixel_id,
                    'google_analytics_id' => $refAffiliate->google_analytics_id,
                ];
            }
        }

        $membership = auth()->id() ? $community->members()->where('user_id', auth()->id())->first() : null;

        return Inertia::render('Communities/About', compact('community', 'affiliate', 'invitedBy', 'membership', 'recentMembers'));
    }
}
