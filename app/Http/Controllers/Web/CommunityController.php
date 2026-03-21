<?php

namespace App\Http\Controllers\Web;

use App\Ai\Agents\LandingPageBuilder;
use App\Actions\Community\CreateCommunity;
use App\Actions\Community\JoinCommunity;
use App\Actions\Community\ManageGallery;
use App\Actions\Community\SendAnnouncement;
use App\Services\SmsService;
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
use App\Models\Comment;
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

        $featured = Community::where('is_featured', true)
            ->with('owner:id,name')
            ->withCount('members')
            ->latest()
            ->get()
            ->map(fn ($c) => [
                'id'           => $c->id,
                'name'         => $c->name,
                'slug'         => $c->slug,
                'description'  => $c->description,
                'cover_image'  => $c->cover_image,
                'avatar'       => $c->avatar,
                'price'        => (float) $c->price,
                'billing_type' => $c->billing_type,
                'category'     => $c->category,
                'members_count'=> $c->members_count,
                'owner'        => ['name' => $c->owner?->name],
            ]);

        return Inertia::render('Communities/Index', [
            'communities' => $query->execute($search, $category, $sort),
            'featured'    => $featured,
            'filters'     => ['search' => $search, 'category' => $category ?: 'All', 'sort' => $sort],
        ]);
    }

    public function store(CreateCommunityRequest $request, CreateCommunity $action): RedirectResponse
    {
        $user = $request->user();

        $plan          = $user->creatorPlan();
        $communityLimit = match ($plan) {
            'pro'   => PHP_INT_MAX,
            'basic' => 3,
            default => 1,
        };

        $existingCount = Community::where('owner_id', $user->id)->count();
        if ($existingCount >= $communityLimit) {
            $upgrade = $plan === 'free' ? 'Upgrade to Basic (3 communities) or Pro (unlimited).' : 'Upgrade to Pro for unlimited communities.';
            return back()->withErrors([
                'plan' => "Your {$plan} plan allows up to {$communityLimit} " . ($communityLimit === 1 ? 'community' : 'communities') . ". {$upgrade}",
            ])->withInput();
        }

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

        $recentComments = Comment::with(['author:id,name,username,avatar', 'post:id,title,community_id'])
            ->where('community_id', $community->id)
            ->whereNull('parent_id')
            ->latest()
            ->take(5)
            ->get(['id', 'post_id', 'user_id', 'content', 'created_at']);

        return Inertia::render('Communities/Show', compact(
            'community', 'membership', 'affiliate', 'adminCount', 'topMembers', 'checklist', 'recentComments'
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

        $courses = $community->courses()
            ->select('id', 'title', 'access_type')
            ->orderBy('position')
            ->get();

        return Inertia::render('Communities/Members', compact('community', 'members', 'totalCount', 'adminCount', 'affiliate', 'courses'));
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

        $levelPerks          = CommunityLevelPerk::where('community_id', $community->id)->pluck('description', 'level')->toArray();
        $canUseIntegrations  = in_array(auth()->user()->creatorPlan(), ['basic', 'pro']);

        return Inertia::render('Communities/Settings', compact('community', 'pricingGate', 'levelPerks', 'canUseIntegrations'));
    }

    public function update(Request $request, Community $community, UpdateCommunity $action): RedirectResponse
    {
        $this->authorize('update', $community);

        $canUseIntegrations = in_array($request->user()->creatorPlan(), ['basic', 'pro']);

        $data = $request->validate([
            'name'                     => ['required', 'string', 'max:255'],
            'description'              => ['nullable', 'string', 'max:2000'],
            'category'                 => ['nullable', 'string', 'in:Tech,Business,Design,Health,Education,Finance,Other'],
            'avatar'      => ['nullable', 'image', 'max:10240'],
            'cover_image' => ['nullable', 'image', 'max:10240'],
            'price'                    => ['nullable', 'numeric', 'min:0'],
            'currency'                 => ['nullable', 'string', 'in:PHP,USD'],
            'billing_type'             => ['nullable', 'string', 'in:monthly,one_time'],
            'is_private'               => ['boolean'],
            'affiliate_commission_rate' => ['nullable', 'integer', 'min:0', 'max:85'],
            'facebook_pixel_id'         => $canUseIntegrations ? ['nullable', 'string', 'max:30', 'regex:/^\d+$/'] : ['prohibited'],
            'tiktok_pixel_id'           => $canUseIntegrations ? ['nullable', 'string', 'max:30', 'regex:/^[A-Z0-9]+$/i'] : ['prohibited'],
            'google_analytics_id'       => $canUseIntegrations ? ['nullable', 'string', 'max:20', 'regex:/^G-[A-Z0-9]+$/i'] : ['prohibited'],
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

        $activeCount = $community->activeSubscribersCount();

        if ($activeCount > 0) {
            // Mark for graceful deletion — no new joins, no renewals, auto-delete when last subscriber expires
            $community->update(['deletion_requested_at' => now()]);

            return back()->with('info', "Deletion scheduled. The community has {$activeCount} active subscriber(s). It will be automatically deleted once all subscriptions expire. No new members can join and subscriptions will not renew.");
        }

        $community->delete();

        return redirect()->route('communities.index')->with('success', 'Community deleted.');
    }

    public function cancelDeletion(Community $community): RedirectResponse
    {
        $this->authorize('delete', $community);
        $community->update(['deletion_requested_at' => null]);

        return back()->with('success', 'Scheduled deletion cancelled. The community is active again.');
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
        $nonAffiliatePlatformFee = round($nonAffiliateGross * $community->platformFeeRate(), 2);
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
            'revenue' => ['gross' => $grossRevenue, 'platform_fee' => $totalPlatformFee, 'platform_fee_rate' => $community->platformFeeRate(), 'affiliate_commission_earned' => $affiliateCommission, 'affiliate_commission_paid' => $affiliatePaid, 'affiliate_commission_pending' => $affiliatePending, 'creator_net' => $totalCreatorNet, 'has_affiliate_data' => $affiliateGross > 0],
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

        if (! in_array($request->user()->creatorPlan(), ['basic', 'pro'])) {
            return back()->withErrors([
                'plan' => 'Email Announcement Blast requires a Basic or Pro plan. Upgrade to send broadcast emails to your members.',
            ]);
        }

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $count = $action->execute($request->user(), $community, $data['subject'], $data['message']);

        return back()->with('success', "Announcement sent to {$count} members.");
    }

    public function updateSmsConfig(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('update', $community);

        $data = $request->validate([
            'sms_provider'    => ['nullable', 'string', 'in:semaphore,philsms,xtreme_sms'],
            'sms_api_key'     => ['nullable', 'string', 'max:255'],
            'sms_sender_name' => ['nullable', 'string', 'max:11'],
            'sms_device_url'  => ['nullable', 'string', 'url', 'max:500'],
        ]);

        $community->update($data);

        return back()->with('success', 'SMS settings saved.');
    }

    public function testSms(Request $request, Community $community, SmsService $sms): RedirectResponse
    {
        $this->authorize('update', $community);

        if (! $community->sms_provider || ! $community->sms_api_key) {
            return back()->withErrors(['sms_test' => 'Save your SMS settings first before testing.']);
        }

        $data  = $request->validate(['phone' => ['required', 'string', 'max:20']]);
        $phone = preg_replace('/\D/', '', $data['phone']);

        if (strlen($phone) < 10) {
            return back()->withErrors(['sms_test' => 'Please enter a valid phone number.']);
        }

        $result = $sms->blast($community, [$phone], "This is a test message from {$community->name} via Curzzo. Your SMS integration is working!");

        if ($result['sent'] > 0) {
            return back()->with('success', "Test SMS sent to {$data['phone']}.");
        }

        return back()->withErrors(['sms_test' => 'Test failed: ' . ($result['errors'][0] ?? 'Unknown error.')]);
    }

    public function sendSmsBlast(Request $request, Community $community, SmsService $sms): RedirectResponse
    {
        $this->authorize('update', $community);

        $data = $request->validate([
            'message'     => ['required', 'string', 'max:1600'],
            'filter_type' => ['required', 'string', 'in:all,new_members,course'],
            'filter_days' => ['nullable', 'integer', 'in:7,14,30'],
            'filter_course_id' => ['nullable', 'integer', 'exists:courses,id'],
        ]);

        if (! $community->sms_provider || ! $community->sms_api_key) {
            return back()->withErrors(['message' => 'SMS provider not configured. Go to Settings → SMS to set it up.']);
        }

        $query = $community->members()
            ->join('users', 'users.id', '=', 'community_members.user_id')
            ->whereNotNull('users.phone')
            ->where('users.phone', '!=', '');

        // Apply audience filter
        if ($data['filter_type'] === 'new_members') {
            $days = $data['filter_days'] ?? 7;
            $query->where('community_members.joined_at', '>=', now()->subDays($days));
        } elseif ($data['filter_type'] === 'course') {
            $courseId = $data['filter_course_id'];
            $query->whereExists(function ($q) use ($courseId) {
                $q->from('course_enrollments')
                    ->whereColumn('course_enrollments.user_id', 'users.id')
                    ->where('course_enrollments.course_id', $courseId)
                    ->where('course_enrollments.status', 'paid');
            });
        }

        $numbers = $query->pluck('users.phone')
            ->map(fn ($p) => preg_replace('/\D/', '', $p))
            ->filter(fn ($p) => strlen($p) >= 10)
            ->values()
            ->toArray();

        if (empty($numbers)) {
            return back()->withErrors(['message' => 'No recipients found with phone numbers for the selected audience.']);
        }

        $result = $sms->blast($community, $numbers, $data['message']);

        $msg = "SMS sent to {$result['sent']} member(s).";
        if ($result['failed'] > 0) {
            $msg .= " {$result['failed']} failed.";
        }

        return back()->with('success', $msg);
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

        $ownerIsPro = in_array($community->owner?->creatorPlan(), ['basic', 'pro']);

        return Inertia::render('Communities/About', compact('community', 'affiliate', 'invitedBy', 'membership', 'recentMembers', 'ownerIsPro'));
    }

    public function generateLandingPage(Request $request, Community $community): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        if ($community->owner_id !== $user->id && !$user->is_super_admin) {
            abort(403);
        }

        if (!$user->hasActiveCreatorPlan()) {
            return response()->json(['error' => 'Creator Pro required.'], 403);
        }

        try {
            $agent  = new LandingPageBuilder([
                'name'        => $community->name,
                'category'    => $community->category,
                'description' => $community->description,
            ]);

            $response = $agent->forUser($user)->prompt(
                'Generate the landing page copy now. Return only valid JSON.'
            );

            $copy = json_decode($response->text, true);

            if (!$copy || !isset($copy['tagline'], $copy['description'], $copy['cta'])) {
                return response()->json(['error' => 'AI returned an unexpected format. Please try again.'], 422);
            }

            return response()->json($copy);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'AI generation failed. Please try again.'], 500);
        }
    }
}
