<?php

namespace App\Http\Controllers\Web;

use App\Ai\Agents\LandingPageBuilder;
use App\Ai\Agents\LandingPageSectionBuilder;
use App\Actions\Community\CreateCommunity;
use App\Actions\Community\EnsureMemberAffiliate;
use App\Actions\Community\JoinCommunity;
use App\Actions\Community\ManageGallery;
use App\Actions\Community\SendAnnouncement;
use App\Actions\Community\SendSmsBlast;
use App\Services\Analytics\CommunityAnalyticsService;
use App\Services\Community\CommunityChecklistService;
use App\Services\TelegramService;
use App\Services\Community\MembershipAccessService;
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
use App\Queries\Community\GetFeaturedCommunities;
use App\Queries\Community\GetInvitedByAffiliate;
use App\Queries\Community\GetLeaderboard;
use App\Queries\Community\ListCommunities;
use App\Queries\Feed\GetCommunityFeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
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

    public function show(Community $community, GetLeaderboard $leaderboard, GetCommunityFeed $feed, CommunityChecklistService $checklistService, EnsureMemberAffiliate $ensureAffiliate, MembershipAccessService $membershipService): Response
    {
        $this->authorize('view', $community);

        $userId = auth()->id();

        $feed->forShow($community, $userId);

        $membership = $userId ? $community->members()->where('user_id', $userId)->first() : null;

        // For paid communities, free-only members without an active subscription
        // cannot post/comment/chat. Null out membership so the UI hides those interactions.
        if ($membership && !$community->isFree() && !$membershipService->hasActiveMembership(auth()->user(), $community)) {
            $membership = null;
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

        $hasLandingPage = !empty($community->landing_page);

        return Inertia::render('Communities/Show', compact(
            'community', 'membership', 'affiliate', 'adminCount', 'topMembers', 'checklist', 'recentComments', 'hasFreeCourses', 'hasLandingPage'
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
        $serverIp   = config('app.server_ip', '');

        return Inertia::render('Communities/Settings', compact(
            'community', 'pricingGate', 'levelPerks', 'canUseIntegrations', 'isPro', 'baseDomain', 'serverIp'
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
            'telegram_bot_token'        => $isPro ? ['nullable', 'string', 'max:100'] : ['prohibited'],
            'telegram_chat_id'          => $isPro ? ['nullable', 'string', 'max:50'] : ['prohibited'],
            'telegram_clear'            => $isPro ? ['sometimes', 'boolean'] : ['prohibited'],
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

        // Capture old value before the update so we can diff
        $oldCustomDomain = $community->custom_domain;

        $oldTelegramToken = $community->telegram_bot_token;

        // Clear telegram if explicitly requested
        if (! empty($data['telegram_clear'])) {
            $data['telegram_bot_token'] = null;
            $data['telegram_chat_id']   = null;
        } elseif (empty($data['telegram_bot_token'])) {
            // Don't overwrite existing token when field is left blank
            unset($data['telegram_bot_token']);
        }
        unset($data['telegram_clear']);

        $action->execute($community, $data, $request->file('avatar'), $request->file('cover_image'));

        // Register / update Telegram webhook when token changes
        $community->refresh();
        $newToken = $community->telegram_bot_token;
        $telegram = app(TelegramService::class);

        if ($newToken && $community->telegram_chat_id && $newToken !== $oldTelegramToken) {
            $webhookUrl = route('webhooks.telegram', ['slug' => $community->slug]);
            $telegram->setWebhook($newToken, $webhookUrl, $telegram->webhookSecret($newToken));
        } elseif (! $newToken && $oldTelegramToken) {
            $telegram->deleteWebhook($oldTelegramToken);
        }

        // Sync custom domain with Ploi (only when it actually changed)
        $newCustomDomain = $community->fresh()->custom_domain;

        if ($oldCustomDomain !== $newCustomDomain) {
            if ($oldCustomDomain) {
                \App\Jobs\RemoveCustomDomain::dispatch($oldCustomDomain);
            }
            if ($newCustomDomain) {
                // Delay slightly — DNS propagation needs time before cert request succeeds
                \App\Jobs\ProvisionCustomDomain::dispatch($newCustomDomain)->delay(now()->addMinutes(2));
            }
        }

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

    public function about(Request $request, Community $community, GetInvitedByAffiliate $invitedByQuery): Response
    {
        $community->load('owner')->loadCount('members');
        $affiliate = auth()->id() ? $community->affiliates()->where('user_id', auth()->id())->first() : null;

        $recentMembers = $community->members()->with('user:id,name,avatar')->latest()->take(8)->get()
            ->map(fn ($m) => ['name' => $m->user?->name, 'avatar' => $m->user?->avatar])
            ->filter(fn ($m) => $m['name'])->values();

        $membership = auth()->id() ? $community->members()->where('user_id', auth()->id())->first() : null;
        $isOwner    = auth()->id() === $community->owner_id;

        $invitedBy  = (!$membership && !$isOwner)
            ? $invitedByQuery->execute($community, $request->cookie('ref_code'))
            : null;

        $ownerIsPro = in_array($community->owner?->creatorPlan(), ['basic', 'pro']);

        return Inertia::render('Communities/About', compact('community', 'affiliate', 'invitedBy', 'membership', 'recentMembers', 'ownerIsPro', 'isOwner'));
    }

    public function landing(Request $request, Community $community, GetInvitedByAffiliate $invitedByQuery): Response|\Illuminate\Http\RedirectResponse
    {
        $community->load('owner')->loadCount('members');
        $community->load(['courses' => fn ($q) => $q->where('is_published', true)->where('access_type', 'inclusive')]);

        $membership = auth()->id() ? $community->members()->where('user_id', auth()->id())->first() : null;
        $ownerIsPro = in_array($community->owner?->creatorPlan(), ['basic', 'pro']);
        $isOwner    = auth()->id() === $community->owner_id;
        $refCode    = $request->query('ref') ?? $request->cookie('ref_code');

        // Redirect non-owners to About if no landing page has been generated yet
        if (!$isOwner && empty($community->landing_page)) {
            $redirect = route('communities.about', $community->slug);
            if ($refCode) {
                Cookie::queue('ref_code', $refCode, 60 * 24 * 30);
                $redirect .= '?modal=true';
            }
            return redirect($redirect);
        }

        $invitedBy = (!$membership && !$isOwner)
            ? $invitedByQuery->execute($community, $refCode)
            : null;

        $affiliate = $invitedBy
            ? $community->affiliates()->where('code', $refCode)->first()
            : (auth()->id() ? $community->affiliates()->where('user_id', auth()->id())->first() : null);

        $courses = $community->courses->values();
        $certifications = $community->certifications()
            ->withCount('questions')
            ->get()
            ->map(fn ($c) => [
                'id'         => $c->id,
                'title'      => $c->title,
                'cert_title' => $c->cert_title,
                'description'=> $c->description,
                'cover_image'=> $c->cover_image ? asset('storage/' . $c->cover_image) : null,
                'price'      => (float) ($c->price ?? 0),
                'questions_count' => $c->questions_count,
            ]);
        $inertia = Inertia::render('Communities/Landing', compact(
            'community', 'affiliate', 'invitedBy', 'membership', 'ownerIsPro', 'isOwner', 'courses', 'certifications'
        ));

        // Persist ?ref= query param as a cookie so it carries through to checkout
        if ($request->query('ref') && !$request->cookie('ref_code')) {
            return $inertia->toResponse($request)->withCookie(cookie('ref_code', $refCode, 60 * 24 * 30));
        }

        return $inertia;
    }

    public function updateLandingPage(Request $request, Community $community): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        if ($community->owner_id !== $user->id && !$user->is_super_admin) {
            abort(403);
        }

        $data = $request->validate([
            'hero.headline'           => 'required|string|max:120',
            'hero.subheadline'        => 'required|string|max:200',
            'hero.cta_label'          => 'required|string|max:50',
            'hero.vsl_url'            => 'nullable|url|max:500',
            'hero.video_type'         => 'nullable|string|in:vsl,embed',
            'hero.embed_html'        => 'nullable|string|max:5000',
            'hero.btn_bg'                         => 'nullable|string|max:20',
            'hero.btn_text'                       => 'nullable|string|max:20',
            'social_proof.stat_label' => 'nullable|string|max:100',
            'social_proof.trust_line' => 'nullable|string|max:100',
            'benefits.headline'       => 'nullable|string|max:100',
            'benefits.items'          => 'nullable|array|max:6',
            'benefits.items.*.icon'   => 'nullable|string|max:10',
            'benefits.items.*.title'  => 'nullable|string|max:80',
            'benefits.items.*.body'   => 'nullable|string|max:300',
            'for_you.headline'        => 'nullable|string|max:100',
            'for_you.points'          => 'nullable|array|max:6',
            'for_you.points.*'        => 'nullable|string|max:120',
            'creator.headline'        => 'nullable|string|max:80',
            'creator.bio'             => 'nullable|string|max:500',
            'creator.name'            => 'nullable|string|max:80',
            'creator.photo'           => 'nullable|url|max:500',
            'testimonials_type'       => 'nullable|string|in:manual,embed',
            'testimonials_embed_html' => 'nullable|string|max:5000',
            'testimonials'            => 'nullable|array|max:6',
            'testimonials.*.name'     => 'nullable|string|max:80',
            'testimonials.*.role'     => 'nullable|string|max:80',
            'testimonials.*.quote'    => 'nullable|string|max:300',
            'faq'                     => 'nullable|array|max:10',
            'faq.*.question'          => 'nullable|string|max:200',
            'faq.*.answer'            => 'nullable|string|max:300',
            'cta_section.headline'    => 'nullable|string|max:120',
            'cta_section.subtext'     => 'nullable|string|max:150',
            'cta_section.cta_label'   => 'nullable|string|max:50',
            'cta_section.bg_image'    => 'nullable|url|max:500',
            'cta_section.btn_bg'                  => 'nullable|string|max:20',
            'cta_section.btn_text'                => 'nullable|string|max:20',
            // Hero bg image
            'hero.bg_image'                       => 'nullable|url|max:500',
            // Offer stack
            'offer_stack.headline'                => 'nullable|string|max:120',
            'offer_stack.items'                   => 'nullable|array|max:8',
            'offer_stack.items.*.name'            => 'nullable|string|max:80',
            'offer_stack.items.*.value'           => 'nullable|string|max:40',
            'offer_stack.items.*.description'     => 'nullable|string|max:200',
            'offer_stack.total_value'             => 'nullable|string|max:40',
            'offer_stack.price'                   => 'nullable|string|max:40',
            'offer_stack.price_note'              => 'nullable|string|max:60',
            'offer_stack.bg_color'                => 'nullable|string|max:20',
            'offer_stack.price_color'             => 'nullable|string|max:20',
            'offer_stack.btn_bg'                  => 'nullable|string|max:20',
            'offer_stack.btn_text'                => 'nullable|string|max:20',
            'offer_stack.cta_label'               => 'nullable|string|max:50',
            // Included courses
            'included_courses_bg_color'           => 'nullable|string|max:20',
            'included_courses_btn_bg'             => 'nullable|string|max:20',
            'included_courses_btn_text'           => 'nullable|string|max:20',
            // Guarantee
            'guarantee.headline'                  => 'nullable|string|max:100',
            'guarantee.days'                      => 'nullable|integer|min:1|max:365',
            'guarantee.body'                      => 'nullable|string|max:400',
            // Price justification
            'price_justification.headline'              => 'nullable|string|max:100',
            'price_justification.options'               => 'nullable|array|max:5',
            'price_justification.options.*.label'       => 'nullable|string|max:80',
            'price_justification.options.*.description' => 'nullable|string|max:300',
            // Section visibility/ordering metadata
            '_sections'               => 'nullable|array',
            '_sections.*.type'        => 'nullable|string|max:50',
            '_sections.*.visible'     => 'nullable|boolean',
        ]);

        // Merge into existing landing page so unreferenced sections are preserved
        $current = $community->landing_page ?? [];
        // Handle _sections separately (full replace, not deep merge)
        $sections = $data['_sections'] ?? null;
        unset($data['_sections']);
        $merged = array_replace_recursive($current, $data);
        if ($sections !== null) {
            $merged['_sections'] = $sections;
        }

        $community->update(['landing_page' => $merged]);

        return response()->json($merged);
    }

    public function generateLandingPage(Request $request, Community $community): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        if ($community->owner_id !== $user->id && !$user->is_super_admin) {
            abort(403);
        }

        try {
            $agent = new LandingPageBuilder([
                'name'         => $community->name,
                'category'     => $community->category,
                'description'  => $community->description,
                'price'        => $community->price,
                'currency'     => $community->currency ?? 'PHP',
                'creator_name' => $user->name,
                'member_count' => $community->members_count ?? $community->members()->count(),
            ]);

            $response = $agent->prompt(
                'Generate the full funnel landing page now. Return only valid JSON.'
            );

            $raw  = trim($response->text);

            // Strip markdown code fences if AI wrapped the JSON
            $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
            $raw = preg_replace('/\s*```$/', '', $raw);

            $copy = json_decode($raw, true);

            if (!$copy || !isset($copy['hero'], $copy['benefits'], $copy['faq'])) {
                \Log::warning('LandingPageBuilder unexpected format', [
                    'community' => $community->slug,
                    'raw'       => substr($raw, 0, 500),
                ]);

                return response()->json(['error' => 'AI returned an unexpected format. Please try again.'], 422);
            }

            // Inject _sections metadata for the new section-based editor
            $copy['_sections'] = [
                ['type' => 'hero',                'visible' => true],
                ['type' => 'social_proof',        'visible' => isset($copy['social_proof'])],
                ['type' => 'benefits',            'visible' => isset($copy['benefits'])],
                ['type' => 'for_you',             'visible' => isset($copy['for_you'])],
                ['type' => 'creator',             'visible' => isset($copy['creator'])],
                ['type' => 'testimonials',        'visible' => !empty($copy['testimonials'])],
                ['type' => 'offer_stack',         'visible' => false],
                ['type' => 'guarantee',           'visible' => false],
                ['type' => 'price_justification', 'visible' => false],
                ['type' => 'faq',                 'visible' => !empty($copy['faq'])],
                ['type' => 'cta_section',         'visible' => true],
            ];

            $community->update(['landing_page' => $copy]);

            return response()->json($copy);
        } catch (\Throwable $e) {
            \Log::error('LandingPageBuilder failed', [
                'community' => $community->slug,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function regenerateSection(Request $request, Community $community): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        if ($community->owner_id !== $user->id && !$user->is_super_admin) {
            abort(403);
        }

        $request->validate([
            'section' => 'required|string|in:hero,social_proof,benefits,for_you,creator,testimonials,faq,cta_section,offer_stack,guarantee,price_justification',
        ]);

        $section = $request->input('section');
        $community->load('owner')->loadCount('members');

        try {
            $agent = new LandingPageSectionBuilder([
                'name'         => $community->name,
                'category'     => $community->category,
                'description'  => $community->description,
                'price'        => $community->price,
                'currency'     => $community->currency ?? 'PHP',
                'creator_name' => $community->owner->name ?? $user->name,
                'member_count' => $community->members_count ?? 0,
                'section'      => $section,
            ]);

            $response = $agent->prompt("Regenerate the '{$section}' section now. Return ONLY valid JSON for this section, no markdown.");
            $raw = trim($response->text);
            $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
            $raw = preg_replace('/\s*```$/', '', $raw);

            $data = json_decode($raw, true);

            if ($data === null) {
                return response()->json(['error' => 'AI returned invalid JSON. Please try again.'], 422);
            }

            $current = $community->landing_page ?? [];
            $current[$section] = $data;
            $community->update(['landing_page' => $current]);

            return response()->json(['section' => $section, 'data' => $data]);
        } catch (\Throwable $e) {
            \Log::error('LandingPageSectionBuilder failed', [
                'community' => $community->slug,
                'section'   => $section,
                'error'     => $e->getMessage(),
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function uploadSectionImage(Request $request, Community $community): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        if ($community->owner_id !== $user->id && !$user->is_super_admin) {
            abort(403);
        }

        $request->validate(['image' => 'required|image|max:5120']);

        $url = asset('storage/' . $request->file('image')->store('landing-images', 'public'));

        return response()->json(['url' => $url]);
    }
}
