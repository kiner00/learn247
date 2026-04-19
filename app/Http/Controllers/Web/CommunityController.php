<?php

namespace App\Http\Controllers\Web;

use App\Actions\Community\CreateCommunity;
use App\Actions\Community\EnsureMemberAffiliate;
use App\Actions\Community\JoinCommunity;
use App\Actions\Community\SendAnnouncement;
use App\Actions\Community\SyncCommunityDomains;
use App\Actions\Community\SyncTelegramWebhook;
use App\Actions\Community\UpdateCommunity;
use App\Actions\Community\UpdateLevelPerks;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCommunityRequest;
use App\Http\Requests\UpdateCommunityRequest;
use App\Jobs\GenerateSingleGalleryImage;
use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Queries\Community\GetFeaturedCommunities;
use App\Queries\Community\GetInvitedByAffiliate;
use App\Queries\Community\GetLeaderboard;
use App\Queries\Community\ListCommunities;
use App\Queries\Feed\GetCommunityFeed;
use App\Services\Analytics\CommunityAnalyticsService;
use App\Services\Community\CommunityChecklistService;
use App\Services\Community\CurzzoAccessService;
use App\Services\Community\CurzzoLimitService;
use App\Services\Community\MembershipAccessService;
use App\Services\Community\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class CommunityController extends Controller
{
    public function index(Request $request, ListCommunities $query, GetFeaturedCommunities $featured): Response
    {
        $search = $request->string('search')->trim()->toString();
        $category = $request->string('category')->trim()->toString();
        $sort = $request->input('sort', 'latest');

        return Inertia::render('Communities/Index', [
            'communities' => $query->execute($search, $category, $sort),
            'featured' => $featured->execute(),
            'filters' => ['search' => $search, 'category' => $category ?: 'All', 'sort' => $sort],
        ]);
    }

    public function store(CreateCommunityRequest $request, CreateCommunity $action, PlanLimitService $planLimit): RedirectResponse
    {
        \Log::info('Community create attempt', [
            'user' => $request->user()->id,
            'data' => $request->except(['avatar', 'cover_image']),
            'avatar' => $request->hasFile('avatar') ? 'yes' : 'no',
            'cover' => $request->hasFile('cover_image') ? 'yes' : 'no',
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
        if ($membership && ! $community->isFree() && ! $membershipService->hasActiveMembership(auth()->user(), $community)) {
            $membership = null;
        }

        $affiliate = $userId ? $ensureAffiliate->execute($community, $userId) : null;

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

        $hasLandingPage = ! empty($community->landing_page);

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

        $members = $query->orderByRaw("CASE role WHEN 'admin' THEN 0 WHEN 'moderator' THEN 1 ELSE 2 END")->paginate(20)->withQueryString();
        $totalCount = $community->members()->count();
        $adminCount = $community->members()->where('role', 'admin')->count();
        $freeCount = $community->members()->where('membership_type', CommunityMember::MEMBERSHIP_FREE)->count();
        $paidCount = $community->members()->where('membership_type', CommunityMember::MEMBERSHIP_PAID)->count();
        $affiliate = auth()->id() ? $community->affiliates()->where('user_id', auth()->id())->first() : null;

        $courses = $community->courses()
            ->select('id', 'title', 'access_type')
            ->orderBy('position')
            ->get();

        $tags = auth()->id() === $community->owner_id
            ? $community->tags()->withCount('members')->orderBy('name')->get()
            : [];

        return Inertia::render('Communities/Members', compact('community', 'members', 'totalCount', 'adminCount', 'freeCount', 'paidCount', 'affiliate', 'courses', 'tags', 'search'));
    }

    public function update(
        UpdateCommunityRequest $request,
        Community $community,
        UpdateCommunity $action,
        SyncTelegramWebhook $syncTelegram,
        SyncCommunityDomains $syncDomains,
    ): RedirectResponse {
        $data = $request->validated();

        $oldSubdomain = $community->subdomain;
        $oldCustomDomain = $community->custom_domain;
        $oldTelegram = $community->telegram_bot_token;

        if (! empty($data['telegram_clear'])) {
            $data['telegram_bot_token'] = null;
            $data['telegram_chat_id'] = null;
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
        $status = Cache::get($cacheKey);

        if ($status && $status['status'] === 'generating') {
            return response()->json(['error' => 'Image generation is already in progress.'], 409);
        }

        $remaining = 8 - $galleryCount;
        Cache::put($cacheKey, ['status' => 'generating', 'progress' => 0, 'total' => $remaining], 300);

        GenerateSingleGalleryImage::dispatch($community, $galleryCount, $remaining);

        return response()->json(['message' => 'Image generation started.'], 202);
    }

    public function aiGalleryStatus(Request $request, Community $community): JsonResponse
    {
        $this->authorize('update', $community);

        $cacheKey = "gallery-generating:{$community->id}";
        $status = Cache::get($cacheKey, ['status' => 'idle']);

        return response()->json($status);
    }

    public function updateLevelPerks(Request $request, Community $community, UpdateLevelPerks $action): RedirectResponse
    {
        $this->authorize('update', $community);

        $data = $request->validate([
            'perks' => ['nullable', 'array'],
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
        $isOwner = $user !== null && $user->id === $community->owner_id;
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
                'value' => $key,
                'label' => $tier['label'],
                'description' => $tier['description'],
            ])->values()
            : [];

        return Inertia::render('Communities/Curzzos', [
            'community' => $community,
            'curzzos' => $curzzos,
            'limitInfo' => $limitInfo,
            'topupPacks' => $limits->getPacks($community),
            'isOwner' => $isOwner,
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

    public function about(Request $request, Community $community, GetInvitedByAffiliate $invitedByQuery): Response
    {
        $community->load('owner')->loadCount('members');
        $affiliate = auth()->id() ? $community->affiliates()->where('user_id', auth()->id())->first() : null;

        $recentMembers = $community->members()->with('user:id,name,avatar')->latest()->take(8)->get()
            ->map(fn ($m) => ['name' => $m->user?->name, 'avatar' => $m->user?->avatar])
            ->filter(fn ($m) => $m['name'])->values();

        $membership = auth()->id() ? $community->members()->where('user_id', auth()->id())->first() : null;
        $isOwner = auth()->id() === $community->owner_id;

        $invitedBy = (! $membership && ! $isOwner)
            ? $invitedByQuery->execute($community, $request->cookie('ref_code'))
            : null;

        $ownerIsPro = in_array($community->owner?->creatorPlan(), ['basic', 'pro']);

        return Inertia::render('Communities/About', compact('community', 'affiliate', 'invitedBy', 'membership', 'recentMembers', 'ownerIsPro', 'isOwner'));
    }
}
