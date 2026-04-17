<?php

namespace App\Http\Controllers\Web;

use App\Actions\Community\CreateCommunity;
use App\Actions\Community\EnsureMemberAffiliate;
use App\Actions\Community\GenerateLandingPage;
use App\Actions\Community\RegenerateLandingSection;
use App\Actions\Community\JoinCommunity;
use App\Actions\Community\ManageGallery;
use App\Actions\Community\SendAnnouncement;
use App\Actions\Community\SendSmsBlast;
use App\Actions\Community\SyncCommunityDomains;
use App\Actions\Community\SyncTelegramWebhook;
use App\Actions\Community\UpdateLandingPage;
use App\Http\Requests\UpdateCommunityRequest;
use App\Http\Requests\UpdateLandingPageRequest;
use App\Services\Analytics\CommunityAnalyticsService;
use App\Services\Community\CommunityChecklistService;
use App\Services\Community\CurzzoAccessService;
use App\Services\Community\CurzzoLimitService;
use App\Services\TelegramService;
use App\Services\Community\MembershipAccessService;
use App\Services\Community\PlanLimitService;
use App\Contracts\SmsProvider;
use App\Services\Email\EmailProviderFactory;
use App\Services\StorageService;
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
use App\Jobs\GenerateSingleGalleryImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
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
        \Log::info('Community create attempt', [
            'user'   => $request->user()->id,
            'data'   => $request->except(['avatar', 'cover_image']),
            'avatar' => $request->hasFile('avatar') ? 'yes' : 'no',
            'cover'  => $request->hasFile('cover_image') ? 'yes' : 'no',
        ]);

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
        $canSearchEmail = auth()->id() === $community->owner_id;
        $userSelect = $canSearchEmail
            ? 'id,name,username,bio,email'
            : 'id,name,username,bio';
        $query = $community->members()->with(["user:{$userSelect}", 'tags:id,name,color']);

        if ($request->filter === 'admin') {
            $query->where('role', 'admin');
        } elseif ($request->filter === 'free') {
            $query->where('membership_type', CommunityMember::MEMBERSHIP_FREE);
        } elseif ($request->filter === 'paid') {
            $query->where('membership_type', CommunityMember::MEMBERSHIP_PAID);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->whereHas('user', function ($q) use ($search, $canSearchEmail) {
                $q->where(function ($q) use ($search, $canSearchEmail) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('username', 'like', "%{$search}%");
                    if ($canSearchEmail) {
                        $q->orWhere('email', 'like', "%{$search}%");
                    }
                });
            });
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

        $tags = auth()->id() === $community->owner_id
            ? $community->tags()->withCount('members')->orderBy('name')->get()
            : [];

        return Inertia::render('Communities/Members', compact('community', 'members', 'totalCount', 'adminCount', 'freeCount', 'paidCount', 'affiliate', 'courses', 'tags', 'search'));
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

    public function update(
        UpdateCommunityRequest $request,
        Community $community,
        UpdateCommunity $action,
        SyncTelegramWebhook $syncTelegram,
        SyncCommunityDomains $syncDomains,
    ): RedirectResponse {
        $data = $request->validated();

        $oldSubdomain    = $community->subdomain;
        $oldCustomDomain = $community->custom_domain;
        $oldTelegram     = $community->telegram_bot_token;

        if (! empty($data['telegram_clear'])) {
            $data['telegram_bot_token'] = null;
            $data['telegram_chat_id']   = null;
        } elseif (empty($data['telegram_bot_token'])) {
            unset($data['telegram_bot_token']);
        }
        unset($data['telegram_clear']);

        $action->execute($community, $data, $request->file('avatar'), $request->file('cover_image'));
        $community->refresh();

        $syncTelegram->execute($community, $oldTelegram);
        $syncDomains->execute($community, $oldSubdomain, $oldCustomDomain);

        return back()->with('success', 'Community updated.');
    }

    public function updateAiInstructions(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('update', $community);

        $data = $request->validate([
            'ai_chatbot_instructions' => ['nullable', 'string', 'max:10000'],
        ]);

        $community->update($data);

        return back()->with('success', 'AI instructions saved.');
    }

    public function addGalleryImage(Request $request, Community $community, ManageGallery $action): RedirectResponse
    {
        $this->authorize('update', $community);
        $request->validate(['image' => ['required', 'image', 'max:15360']]);

        $action->addImage($community, $request->file('image'));

        return back()->with('success', 'Image added!');
    }

    public function removeGalleryImage(Request $request, Community $community, int $index, ManageGallery $action): RedirectResponse
    {
        $this->authorize('update', $community);

        $action->removeImage($community, $index);

        return back()->with('success', 'Image removed!');
    }

    public function aiGenerateGallery(Request $request, Community $community): JsonResponse
    {
        $this->authorize('update', $community);

        if ($request->user()->creatorPlan() !== 'pro') {
            return response()->json(['error' => 'AI Gallery generation requires a PRO plan.'], 403);
        }

        $galleryCount = count($community->gallery_images ?? []);
        if ($galleryCount >= 8) {
            return response()->json(['error' => 'Gallery is full (8 images). Remove one to generate more.'], 422);
        }

        $cacheKey = "gallery-generating:{$community->id}";
        $status   = Cache::get($cacheKey);

        if ($status && $status['status'] === 'generating') {
            return response()->json(['error' => 'Image generation is already in progress.'], 409);
        }

        $remaining = 8 - $galleryCount;
        Cache::put($cacheKey, ['status' => 'generating', 'progress' => 0, 'total' => $remaining], 300);

        GenerateSingleGalleryImage::dispatch($community, $galleryCount, $remaining);

        return response()->json(['message' => 'Image generation started.'], 202);
    }

    public function reorderGallery(Request $request, Community $community): JsonResponse
    {
        $this->authorize('update', $community);

        $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'min:0'],
        ]);

        $gallery  = $community->gallery_images ?? [];
        $order    = $request->input('order');

        // Validate that the order array contains valid indices
        if (count($order) !== count($gallery) || array_diff($order, array_keys($gallery))) {
            return response()->json(['error' => 'Invalid order.'], 422);
        }

        $reordered = array_map(fn ($i) => $gallery[$i], $order);
        $community->update(['gallery_images' => $reordered]);

        return response()->json(['message' => 'Gallery reordered.']);
    }

    public function aiGalleryStatus(Request $request, Community $community): JsonResponse
    {
        $this->authorize('update', $community);

        $cacheKey = "gallery-generating:{$community->id}";
        $status   = Cache::get($cacheKey, ['status' => 'idle']);

        return response()->json($status);
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

    public function curzzos(Community $community, CurzzoAccessService $access, CurzzoLimitService $limits): Response
    {
        $user = auth()->user();

        $selectFields = ['id', 'name', 'description', 'avatar', 'cover_image', 'preview_video', 'preview_video_sound', 'access_type', 'price', 'currency', 'billing_type'];
        $isOwner      = $user !== null && $user->id === $community->owner_id;
        if ($isOwner) {
            $selectFields = array_merge($selectFields, ['instructions', 'personality', 'model_tier', 'affiliate_commission_rate', 'is_active']);
        }

        $curzzos = $community->curzzos()
            ->orderBy('position')
            ->when(! $isOwner, fn ($q) => $q->where('is_active', true))
            ->select($selectFields)
            ->get();

        $context = $access->buildContext($user, $community, $curzzos->pluck('id'));
        $curzzos = $curzzos->map(function ($bot) use ($access, $context) {
            $bot->has_access = $access->hasAccess($bot, $context);
            return $bot;
        });

        $limitInfo = $user ? $limits->canSendMessage($user, $community) : [
            'allowed' => false, 'daily_limit' => 0, 'daily_used' => 0, 'topup_remaining' => 0,
        ];

        $modelTiers = $isOwner
            ? collect(config('curzzos.tiers'))->map(fn ($tier, $key) => [
                'value'       => $key,
                'label'       => $tier['label'],
                'description' => $tier['description'],
            ])->values()
            : [];

        return Inertia::render('Communities/Curzzos', [
            'community'  => $community,
            'curzzos'    => $curzzos,
            'limitInfo'  => $limitInfo,
            'topupPacks' => $limits->getPacks($community),
            'isOwner'    => $isOwner,
            'modelTiers' => $modelTiers,
        ]);
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

    public function testSms(Request $request, Community $community, SmsProvider $sms): RedirectResponse
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

    // ─── Resend Email Config ─────────────────────────────────────────────────

    public function updateResendConfig(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('update', $community);

        $providerIds = array_keys(EmailProviderFactory::PROVIDERS);

        $data = $request->validate([
            'email_provider'    => ['nullable', 'string', 'in:' . implode(',', $providerIds)],
            'resend_api_key'    => ['nullable', 'string', 'max:500'],
            'resend_from_email' => ['nullable', 'email', 'max:255'],
            'resend_from_name'  => ['nullable', 'string', 'max:255'],
            'resend_reply_to'   => ['nullable', 'email', 'max:255'],
        ]);

        // Validate the API key if provided
        if (! empty($data['resend_api_key']) && ! empty($data['email_provider'])) {
            $community->resend_api_key = $data['resend_api_key'];
            $community->email_provider = $data['email_provider'];

            try {
                $provider = EmailProviderFactory::make($community);
                if (! $provider->validateApiKey($community)) {
                    return back()->withErrors(['resend_api_key' => 'Invalid API key for ' . ($data['email_provider'] ?? 'provider') . '.']);
                }
            } catch (\Exception $e) {
                return back()->withErrors(['resend_api_key' => 'Could not validate the API key: ' . $e->getMessage()]);
            }
        }

        $community->update($data);

        return back()->with('success', 'Email settings saved.');
    }

    public function resendAddDomain(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('update', $community);

        if (! $community->resend_api_key) {
            return back()->withErrors(['resend_domain' => 'Save your Resend API key first.']);
        }

        $data = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
        ]);

        try {
            $provider = EmailProviderFactory::make($community);
            $domain = $provider->addDomain($community, $data['domain']);

            $community->update([
                'resend_domain_id'     => $domain['id'],
                'resend_domain_status' => $domain['status'] ?? 'pending',
            ]);

            return back()->with('success', 'Domain added. Please configure the DNS records shown below, then click Verify.');
        } catch (\Exception $e) {
            return back()->withErrors(['resend_domain' => 'Failed to add domain: ' . $e->getMessage()]);
        }
    }

    public function resendVerifyDomain(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('update', $community);

        if (! $community->resend_api_key || ! $community->resend_domain_id) {
            return back()->withErrors(['resend_domain' => 'No domain to verify.']);
        }

        try {
            $provider = EmailProviderFactory::make($community);
            $domain = $provider->verifyDomain($community, $community->resend_domain_id);

            $community->update([
                'resend_domain_status' => $domain['status'] ?? 'pending',
            ]);

            $status = $domain['status'] ?? 'pending';

            return $status === 'verified'
                ? back()->with('success', 'Domain verified successfully!')
                : back()->with('success', "Domain status: {$status}. DNS propagation may take a few minutes.");
        } catch (\Exception $e) {
            return back()->withErrors(['resend_domain' => 'Verification failed: ' . $e->getMessage()]);
        }
    }

    public function resendGetDomain(Request $request, Community $community)
    {
        $this->authorize('update', $community);

        if (! $community->resend_api_key || ! $community->resend_domain_id) {
            return response()->json(['error' => 'No domain configured.'], 422);
        }

        try {
            $provider = EmailProviderFactory::make($community);
            $domain = $provider->getDomain($community, $community->resend_domain_id);

            $community->update([
                'resend_domain_status' => $domain['status'] ?? 'pending',
            ]);

            return response()->json([
                'id'      => $domain['id'],
                'name'    => $domain['name'],
                'status'  => $domain['status'],
                'records' => $domain['records'] ?? [],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function resendTestEmail(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('update', $community);

        if (! $community->resend_api_key) {
            return back()->withErrors(['resend_test' => 'Save your Resend API key first.']);
        }

        $data = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ]);

        $fromName = $community->resend_from_name ?? $community->name;

        // For Resend: use onboarding@resend.dev as sandbox sender if domain not verified
        $fromEmail = $community->resend_from_email ?? 'onboarding@resend.dev';
        $isResend  = ($community->email_provider ?? 'resend') === 'resend';

        try {
            $provider = EmailProviderFactory::make($community);

            // Try with configured from email first
            $replyTo = $community->resend_reply_to ? [$community->resend_reply_to] : [];

            try {
                $provider->sendEmail($community, [
                    'from'     => "{$fromName} <{$fromEmail}>",
                    'to'       => [$data['test_email']],
                    'subject'  => "Test email from {$community->name}",
                    'html'     => "<p>This is a test email from <strong>{$community->name}</strong> via Curzzo. Your email integration is working!</p>",
                    'reply_to' => $replyTo,
                ]);

                return back()->with('success', "Test email sent to {$data['test_email']}.");
            } catch (\Exception $e) {
                // If domain not verified on Resend, retry with sandbox sender
                if ($isResend && str_contains($e->getMessage(), 'not verified')) {
                    $provider->sendEmail($community, [
                        'from'    => "Curzzo <onboarding@resend.dev>",
                        'to'      => [$data['test_email']],
                        'subject' => "[Test] Email from {$community->name}",
                        'html'    => "<p>This is a test email from <strong>{$community->name}</strong> via Curzzo.</p><p style='color:#666;font-size:13px;'>Sent from Resend sandbox (onboarding@resend.dev) because your domain is not yet verified. Verify your domain at <a href='https://resend.com/domains'>resend.com/domains</a> to send from your own address.</p>",
                    ]);

                    return back()->with('success', "Test sent via Resend sandbox to {$data['test_email']}. Verify your domain at resend.com/domains to use your own from address.");
                }

                throw $e;
            }
        } catch (\Exception $e) {
            return back()->withErrors(['resend_test' => 'Test failed: ' . $e->getMessage()]);
        }
    }

    // ─── SMS Blast ───────────────────────────────────────────────────────────

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

    public function landing(Request $request, Community $community, GetInvitedByAffiliate $invitedByQuery): Response|\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        $community->load('owner')->loadCount('members');

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

        // Load all published courses; let the owner pick which ones appear on the LP
        $allPublished = $community->courses()->where('is_published', true)->get();
        $selectedIds  = $community->landing_page['included_courses_selected'] ?? null;
        // For visitors: show only selected courses (or fall back to inclusive if nothing selected yet)
        $filtered = $selectedIds !== null
            ? $allPublished->whereIn('id', $selectedIds)->values()
            : $allPublished->where('access_type', 'inclusive')->values();
        $courses = $filtered->map(fn ($c) => [
            'id'            => $c->id,
            'title'         => $c->title,
            'description'   => $c->description,
            'cover_image'   => $c->cover_image,
            'preview_video'       => $c->preview_video,
            'preview_video_sound' => (bool) $c->preview_video_sound,
            'access_type'   => $c->access_type,
            'price'         => $c->price,
        ]);
        // Owner also gets the full list so they can toggle checkboxes
        $allCourses = $isOwner ? $allPublished->values() : [];
        $certifications = $community->certifications()
            ->withCount('questions')
            ->get()
            ->map(fn ($c) => [
                'id'         => $c->id,
                'title'      => $c->title,
                'cert_title' => $c->cert_title,
                'description'=> $c->description,
                'cover_image'=> $c->cover_image ?: null,
                'price'      => (float) ($c->price ?? 0),
                'questions_count' => $c->questions_count,
            ]);
        $allCurzzos = $community->curzzos()
            ->where('is_active', true)
            ->select('id', 'name', 'description', 'avatar', 'cover_image', 'preview_video', 'preview_video_sound', 'access_type', 'price', 'currency', 'billing_type')
            ->orderBy('position')
            ->get();
        $selectedCurzzoIds = $community->landing_page['curzzos_selected'] ?? null;
        // Visitors see only selected bots (fall back to all when nothing selected yet).
        // Owners see the full list so they can toggle selection checkboxes.
        $curzzos = $selectedCurzzoIds !== null
            ? $allCurzzos->whereIn('id', $selectedCurzzoIds)->values()
            : $allCurzzos;
        $lp = $community->landing_page ?? [];
        $brand = $community->brand_context ?? [];
        $ogTitle = $lp['hero_headline'] ?? $community->name;
        $ogDesc  = $brand['social_share_description']
            ?? $lp['hero_subheadline']
            ?? $community->description
            ?? '';
        $ogImage = $lp['hero_image'] ?? $community->cover_image ?? null;

        View::share('ogMeta', [
            'title'       => $ogTitle,
            'description' => Str::limit(strip_tags($ogDesc), 200),
            'image'       => $ogImage,
            'url'         => url("/communities/{$community->slug}/landing"),
        ]);

        $inertia = Inertia::render('Communities/Landing', compact(
            'community', 'affiliate', 'invitedBy', 'membership', 'ownerIsPro', 'isOwner', 'courses', 'allCourses', 'certifications', 'curzzos', 'allCurzzos'
        ));

        // Persist ?ref= query param as a cookie so it carries through to checkout
        if ($request->query('ref') && !$request->cookie('ref_code')) {
            return $inertia->toResponse($request)->withCookie(cookie('ref_code', $refCode, 60 * 24 * 30));
        }

        return $inertia;
    }

    public function updateLandingPage(UpdateLandingPageRequest $request, Community $community, UpdateLandingPage $action): \Illuminate\Http\JsonResponse
    {
        return response()->json($action->execute($community, $request->validated()));
    }


    public function generateLandingPage(Request $request, Community $community, GenerateLandingPage $action): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        if ($community->owner_id !== $user->id && ! $user->is_super_admin) {
            abort(403);
        }

        try {
            $copy = $action->execute($community, $user);

            return response()->json($copy);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Log::error('CommunityController@generateLandingPage failed', [
                'community' => $community->slug,
                'error'     => $e->getMessage(),
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function regenerateSection(Request $request, Community $community, RegenerateLandingSection $action): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        if ($community->owner_id !== $user->id && ! $user->is_super_admin) {
            abort(403);
        }

        $request->validate([
            'section' => 'required|string|in:hero,social_proof,benefits,for_you,creator,testimonials,faq,cta_section,offer_stack,guarantee,price_justification',
        ]);

        try {
            $result = $action->execute($community, $request->input('section'));

            return response()->json($result);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Log::error('CommunityController@regenerateSection failed', [
                'community' => $community->slug,
                'section'   => $request->input('section'),
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

        $request->validate(['image' => 'required|image|max:15360']);

        $url = app(StorageService::class)->upload($request->file('image'), 'landing-images');

        return response()->json(['url' => $url]);
    }

    public function uploadSectionVideo(Request $request, Community $community, PlanLimitService $planLimit): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        if ($community->owner_id !== $user->id && ! $user->is_super_admin) {
            abort(403);
        }

        $owner = $community->owner;
        if (! $user->is_super_admin && ! $planLimit->canUploadVideo($owner)) {
            return response()->json(['error' => 'Video uploads require a Pro plan.'], 403);
        }

        $request->validate([
            'filename'     => ['required', 'string', 'max:255'],
            'content_type' => ['required', 'string', 'in:video/mp4,video/quicktime,video/webm,video/x-msvideo'],
            'size'         => ['required', 'integer', 'min:1'],
        ]);

        $maxBytes = $planLimit->maxVideoSizeMb($owner->creatorPlan()) * 1024 * 1024;
        if (! $user->is_super_admin && $request->size > $maxBytes) {
            return response()->json([
                'error' => 'File too large. Maximum size is ' . $planLimit->maxVideoSizeMb($owner->creatorPlan()) . 'MB.',
            ], 422);
        }

        $extension = pathinfo($request->filename, PATHINFO_EXTENSION) ?: 'mp4';
        $key       = 'landing-videos/' . Str::uuid() . '.' . $extension;

        $client  = Storage::disk('s3')->getClient();
        $command = $client->getCommand('PutObject', [
            'Bucket'      => config('filesystems.disks.s3.bucket'),
            'Key'         => $key,
            'ContentType' => $request->content_type,
        ]);

        $presigned = $client->createPresignedRequest($command, '+30 minutes');

        return response()->json([
            'upload_url' => (string) $presigned->getUri(),
            'key'        => $key,
            'url'        => Storage::disk('s3')->url($key),
        ]);
    }
}
