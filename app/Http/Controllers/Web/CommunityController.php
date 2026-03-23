<?php

namespace App\Http\Controllers\Web;

use App\Ai\Agents\LandingPageBuilder;
use App\Actions\Community\CreateCommunity;
use App\Actions\Community\EnsureMemberAffiliate;
use App\Actions\Community\JoinCommunity;
use App\Models\Affiliate;
use App\Actions\Community\ManageGallery;
use App\Actions\Community\SendAnnouncement;
use App\Actions\Community\SendSmsBlast;
use App\Services\Analytics\CommunityAnalyticsService;
use App\Services\Community\CommunityChecklistService;
use App\Services\Community\PlanLimitService;
use App\Services\SmsService;
use App\Actions\Community\UpdateCommunity;
use App\Actions\Community\UpdateLevelPerks;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCommunityRequest;
use App\Models\Community;
use App\Models\CommunityLevelPerk;
use App\Models\CommunityMember;
use App\Models\Comment;
use App\Models\Subscription;
use App\Queries\Community\GetFeaturedCommunities;
use App\Queries\Community\GetLeaderboard;
use App\Queries\Community\ListCommunities;
use App\Queries\Feed\GetCommunityFeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CommunityController extends Controller
{
    public function index(Request $request, ListCommunities $query, GetFeaturedCommunities $featured): Response
    {
        $search   = $request->string('search')->trim()->toString();
        $category = $request->string('category')->trim()->toString();
        $sort     = $request->input('sort', 'latest');

        return Inertia::render('Communities/Index', [
            'communities' => $query->execute($search, $category, $sort),
            'featured'    => $featured->execute(),
            'filters'     => ['search' => $search, 'category' => $category ?: 'All', 'sort' => $sort],
        ]);
    }

    public function store(CreateCommunityRequest $request, CreateCommunity $action, PlanLimitService $planLimit): RedirectResponse
    {
        $user = $request->user();

        if (! $planLimit->canCreateCommunity($user)) {
            return back()->withErrors(['plan' => $planLimit->communityLimitError($user)])->withInput();
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

    public function show(Community $community, GetLeaderboard $leaderboard, GetCommunityFeed $feed, CommunityChecklistService $checklistService, EnsureMemberAffiliate $ensureAffiliate): Response
    {
        $this->authorize('view', $community);

        $userId = auth()->id();

        $feed->forShow($community, $userId);

        $membership = $userId ? $community->members()->where('user_id', $userId)->first() : null;

        // For paid communities, free-only subscribers cannot post/comment/chat.
        // Null out membership so the UI correctly hides those interactions.
        if ($membership && !$community->isFree() && $membership->membership_type === CommunityMember::MEMBERSHIP_FREE) {
            $hasActiveSub = Subscription::where('community_id', $community->id)
                ->where('user_id', $userId)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists();

            if (!$hasActiveSub) {
                $membership = null;
            }
        }
        $affiliate  = $userId ? $ensureAffiliate->execute($community, $userId) : null;

        $adminCount = $community->members()->where('role', CommunityMember::ROLE_ADMIN)->count();
        $topMembers = $leaderboard->topMembers($community);

        $checklist = ($userId && $community->owner_id === $userId)
            ? $checklistService->compute($community)
            : null;

        $recentComments = Comment::with(['author:id,name,username,avatar', 'post:id,title,community_id'])
            ->where('community_id', $community->id)
            ->whereNull('parent_id')
            ->latest()
            ->take(5)
            ->get(['id', 'post_id', 'user_id', 'content', 'created_at']);

        $hasFreeCourses = $community->courses()->where('access_type', 'free')->exists();

        return Inertia::render('Communities/Show', compact(
            'community', 'membership', 'affiliate', 'adminCount', 'topMembers', 'checklist', 'recentComments', 'hasFreeCourses'
        ));
    }

    public function members(Community $community, Request $request): Response
    {
        $query = $community->members()->with('user:id,name,username,bio');

        if ($request->filter === 'admin') {
            $query->where('role', 'admin');
        } elseif ($request->filter === 'free') {
            $query->where('membership_type', CommunityMember::MEMBERSHIP_FREE);
        } elseif ($request->filter === 'paid') {
            $query->where('membership_type', CommunityMember::MEMBERSHIP_PAID);
        }

        $members    = $query->orderByRaw("CASE role WHEN 'admin' THEN 0 WHEN 'moderator' THEN 1 ELSE 2 END")->paginate(20)->withQueryString();
        $totalCount = $community->members()->count();
        $adminCount = $community->members()->where('role', 'admin')->count();
        $freeCount  = $community->members()->where('membership_type', CommunityMember::MEMBERSHIP_FREE)->count();
        $paidCount  = $community->members()->where('membership_type', CommunityMember::MEMBERSHIP_PAID)->count();
        $affiliate  = auth()->id() ? $community->affiliates()->where('user_id', auth()->id())->first() : null;

        $courses = $community->courses()
            ->select('id', 'title', 'access_type')
            ->orderBy('position')
            ->get();

        return Inertia::render('Communities/Members', compact('community', 'members', 'totalCount', 'adminCount', 'freeCount', 'paidCount', 'affiliate', 'courses'));
    }

    public function settings(Community $community, PlanLimitService $planLimit): Response
    {
        $this->authorize('update', $community);

        $pricingGate        = $planLimit->pricingGate($community);
        $levelPerks         = CommunityLevelPerk::where('community_id', $community->id)->pluck('description', 'level')->toArray();
        $canUseIntegrations = $planLimit->canSendAnnouncement(auth()->user());
        $isPro              = auth()->user()->creatorPlan() === 'pro';

        // Base domain shown in the subdomain preview (strips port for display)
        $appHost    = parse_url(config('app.url'), PHP_URL_HOST) ?? 'curzzo.com';
        $baseDomain = explode(':', $appHost)[0];

        return Inertia::render('Communities/Settings', compact(
            'community', 'pricingGate', 'levelPerks', 'canUseIntegrations', 'isPro', 'baseDomain'
        ));
    }

    public function update(Request $request, Community $community, UpdateCommunity $action): RedirectResponse
    {
        $this->authorize('update', $community);

        $plan               = $request->user()->creatorPlan();
        $canUseIntegrations = in_array($plan, ['basic', 'pro']);
        $isPro              = $plan === 'pro';

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
            // Domain fields
            'subdomain'    => [
                'nullable', 'string', 'max:63',
                'regex:/^[a-z0-9][a-z0-9-]*[a-z0-9]$|^[a-z0-9]$/',
                Rule::unique('communities', 'subdomain')->ignore($community->id),
            ],
            'custom_domain' => [
                Rule::prohibitedIf(! $isPro),
                'nullable', 'string', 'max:253',
                'regex:/^([a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/',
                Rule::unique('communities', 'custom_domain')->ignore($community->id),
            ],
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

    public function analytics(Community $community, CommunityAnalyticsService $analyticsService): Response
    {
        $this->authorize('viewAnalytics', $community);

        $data = $analyticsService->build($community);

        return Inertia::render('Communities/Analytics', array_merge(['community' => $community], $data));
    }

    public function join(Request $request, Community $community, JoinCommunity $action): RedirectResponse
    {
        $action->execute($request->user(), $community);

        return back()->with('success', 'You have joined the community!');
    }

    public function announce(Request $request, Community $community, SendAnnouncement $action, PlanLimitService $planLimit): RedirectResponse
    {
        $this->authorize('update', $community);

        if (! $planLimit->canSendAnnouncement($request->user())) {
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

    public function sendSmsBlast(Request $request, Community $community, SendSmsBlast $action): RedirectResponse
    {
        $this->authorize('update', $community);

        $data = $request->validate([
            'message'          => ['required', 'string', 'max:1600'],
            'filter_type'      => ['required', 'string', 'in:all,new_members,course'],
            'filter_days'      => ['nullable', 'integer', 'in:7,14,30'],
            'filter_course_id' => ['nullable', 'integer', 'exists:courses,id'],
        ]);

        if (! $community->sms_provider || ! $community->sms_api_key) {
            return back()->withErrors(['message' => 'SMS provider not configured. Go to Settings → SMS to set it up.']);
        }

        $result = $action->execute($community, $data);

        if ($result['no_recipients']) {
            return back()->withErrors(['message' => 'No recipients found with phone numbers for the selected audience.']);
        }

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
