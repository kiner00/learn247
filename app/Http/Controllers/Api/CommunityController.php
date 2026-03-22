<?php

namespace App\Http\Controllers\Api;

use App\Actions\Community\CreateCommunity;
use App\Actions\Community\JoinCommunity;
use App\Actions\Community\ManageGallery;
use App\Actions\Community\SendAnnouncement;
use App\Actions\Community\UpdateCommunity;
use App\Actions\Community\UpdateLevelPerks;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCommunityRequest;
use App\Http\Resources\CommunityResource;
use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\CommunityLevelPerk;
use App\Models\CommunityMember;
use App\Models\LessonCompletion;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\QuizAttempt;
use App\Models\Subscription;
use App\Queries\Community\GetLeaderboard;
use App\Queries\Community\ListCommunities;
use App\Queries\Feed\GetCommunityFeed;
use App\Queries\Payout\CalculateEligibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class CommunityController extends Controller
{
    public function index(Request $request, ListCommunities $query): AnonymousResourceCollection
    {
        $communities = $query->execute(
            $request->string('search')->trim()->toString(),
            $request->string('category')->trim()->toString(),
            $request->input('sort', 'latest'),
        );

        if ($userId = $request->user()?->id) {
            $memberIds = CommunityMember::where('user_id', $userId)
                ->pluck('community_id')
                ->flip();

            $communities->each(function (Community $c) use ($memberIds) {
                $c->is_member = $memberIds->has($c->id);
                $c->is_admin  = false;
            });
        }

        return CommunityResource::collection($communities);
    }

    public function show(Request $request, Community $community): JsonResponse
    {
        $community->load('owner')->loadCount('members');

        $userId     = $request->user()?->id;
        $membership = $userId ? $community->members()->where('user_id', $userId)->first() : null;
        $isOwner    = $userId && $community->owner_id === $userId;

        $hasAccess = $isOwner
            || ($community->isFree() && $membership)
            || (! $community->isFree() && Subscription::where('community_id', $community->id)
                ->where('user_id', $userId)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists());

        return response()->json([
            'community'  => new CommunityResource($community),
            'membership' => $membership ? [
                'role'      => $membership->role,
                'points'    => $membership->points,
                'level'     => CommunityMember::computeLevel($membership->points),
                'joined_at' => $membership->joined_at,
            ] : null,
            'has_access' => $hasAccess,
        ]);
    }

    public function store(CreateCommunityRequest $request, CreateCommunity $action): JsonResponse
    {
        $community = $action->execute(
            $request->user(),
            $request->validated(),
            $request->file('avatar'),
            $request->file('cover_image'),
        );

        return response()->json([
            'message'   => 'Community created.',
            'community' => new CommunityResource($community),
        ], 201);
    }

    public function update(Request $request, Community $community, UpdateCommunity $action): JsonResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $data = $request->validate([
            'name'                     => ['required', 'string', 'max:255'],
            'description'              => ['nullable', 'string', 'max:2000'],
            'category'                 => ['nullable', 'string', 'in:Tech,Business,Design,Health,Education,Finance,Other'],
            'avatar'                   => ['nullable', 'image', 'max:10240'],
            'cover_image'              => ['nullable', 'image', 'max:10240'],
            'price'                    => ['nullable', 'numeric', 'min:0'],
            'currency'                 => ['nullable', 'string', 'in:PHP,USD'],
            'is_private'               => ['boolean'],
            'affiliate_commission_rate' => ['nullable', 'integer', 'min:0', 'max:85'],
        ]);

        $action->execute($community, $data, $request->file('avatar'), $request->file('cover_image'));

        return response()->json(['message' => 'Community updated.']);
    }

    public function destroy(Request $request, Community $community): JsonResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);
        $community->delete();

        return response()->json(['message' => 'Community deleted.']);
    }

    public function join(Request $request, Community $community, JoinCommunity $action): JsonResponse
    {
        $action->execute($request->user(), $community);

        return response()->json(['message' => 'You have joined the community!'], 201);
    }

    public function about(Request $request, Community $community): JsonResponse
    {
        $community->load('owner:id,name,avatar,bio')->loadCount('members');

        $recentMembers = $community->members()->with('user:id,name,avatar')->latest()->take(8)->get()
            ->map(fn ($m) => ['name' => $m->user?->name, 'avatar' => $m->user?->avatar])
            ->filter(fn ($m) => $m['name'])->values();

        $gallery = collect($community->gallery_images ?? [])->map(fn ($path) => Storage::url($path));

        return response()->json([
            'community'      => new CommunityResource($community),
            'recent_members' => $recentMembers,
            'gallery'        => $gallery,
        ]);
    }

    public function members(Request $request, Community $community): JsonResponse
    {
        $query = $community->members()->with('user:id,name,username,bio,avatar');

        if ($request->filter === 'admin') {
            $query->where('role', 'admin');
        }

        $members = $query->orderByRaw("CASE role WHEN 'admin' THEN 0 WHEN 'moderator' THEN 1 ELSE 2 END")
            ->paginate(20)->withQueryString();

        return response()->json([
            'members'     => $members,
            'total_count' => $community->members()->count(),
            'admin_count' => $community->members()->where('role', 'admin')->count(),
        ]);
    }

    public function settings(Request $request, Community $community): JsonResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

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

        return response()->json([
            'community'    => new CommunityResource($community),
            'pricing_gate' => $pricingGate,
            'level_perks'  => $levelPerks,
        ]);
    }

    public function analytics(Request $request, Community $community, CalculateEligibility $eligibility): JsonResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $activeCount = Subscription::where('community_id', $community->id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereHas('payments', fn ($q) => $q->where('status', Payment::STATUS_PAID))
            ->count();

        $monthlyRevenue = $activeCount * (float) $community->price;
        $totalMembers   = $community->members()->count();

        $grossRevenue = (float) Payment::where('community_id', $community->id)->where('status', Payment::STATUS_PAID)->sum('amount');

        $conversionBase = AffiliateConversion::whereHas('affiliate', fn ($q) => $q->where('community_id', $community->id));
        $affiliateCommission = (float) (clone $conversionBase)->sum('commission_amount');

        $nonAffiliateGross       = round($grossRevenue - (float) (clone $conversionBase)->sum('sale_amount'), 2);
        $nonAffiliatePlatformFee = round(max(0, $nonAffiliateGross) * $community->platformFeeRate(), 2);
        $totalPlatformFee        = round((float) (clone $conversionBase)->sum('platform_fee') + $nonAffiliatePlatformFee, 2);
        $totalCreatorNet         = round((float) (clone $conversionBase)->sum('creator_amount') + max(0, $nonAffiliateGross) - $nonAffiliatePlatformFee, 2);

        $courses = $community->courses()->with('modules.lessons')->get();
        $courseStats = $courses->map(function ($course) {
            $paidModules  = $course->modules->where('is_free', false);
            $lessonIds    = $paidModules->flatMap(fn ($m) => $m->lessons->pluck('id'));
            $totalLessons = $lessonIds->count();
            $completedMembers = $totalLessons > 0
                ? LessonCompletion::whereIn('lesson_id', $lessonIds)->selectRaw('user_id, count(*) as cnt')->groupBy('user_id')->havingRaw('cnt >= ?', [$totalLessons])->count()
                : 0;
            $quizIds      = \App\Models\Quiz::whereIn('lesson_id', $lessonIds)->pluck('id');
            $quizAttempts = QuizAttempt::whereIn('quiz_id', $quizIds)->count();
            $quizPasses   = QuizAttempt::whereIn('quiz_id', $quizIds)->where('passed', true)->count();

            return [
                'id' => $course->id, 'title' => $course->title, 'total_lessons' => $totalLessons,
                'total_completions' => LessonCompletion::whereIn('lesson_id', $lessonIds)->count(),
                'completed_members' => $completedMembers,
                'quiz_attempts' => $quizAttempts, 'quiz_passes' => $quizPasses,
                'quiz_pass_rate' => $quizAttempts > 0 ? round($quizPasses / $quizAttempts * 100) : null,
            ];
        });

        [$eligibleNow, $lockedAmount, $nextEligibleDate] = $eligibility->forOwner($community);

        $pendingPayoutRequest = PayoutRequest::where('community_id', $community->id)
            ->where('type', PayoutRequest::TYPE_OWNER)->where('status', PayoutRequest::STATUS_PENDING)->latest()->first();

        return response()->json([
            'stats'   => ['monthly_revenue' => $monthlyRevenue, 'active_subscriptions' => $activeCount, 'total_members' => $totalMembers, 'free_members' => $totalMembers - $activeCount],
            'revenue' => ['gross' => $grossRevenue, 'platform_fee' => $totalPlatformFee, 'affiliate_commission' => $affiliateCommission, 'creator_net' => $totalCreatorNet],
            'payout'  => ['eligible_now' => $eligibleNow, 'locked_amount' => $lockedAmount, 'next_eligible_date' => $nextEligibleDate, 'pending_request' => $pendingPayoutRequest ? ['amount' => $pendingPayoutRequest->amount] : null],
            'course_stats' => $courseStats->values(),
        ]);
    }

    public function announce(Request $request, Community $community, SendAnnouncement $action): JsonResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $count = $action->execute($request->user(), $community, $data['subject'], $data['message']);

        return response()->json(['message' => "Announcement sent to {$count} members."]);
    }

    public function addGalleryImage(Request $request, Community $community, ManageGallery $action): JsonResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);
        $request->validate(['image' => ['required', 'image', 'max:10240']]);

        $action->addImage($community, $request->file('image'));

        return response()->json(['message' => 'Image added.'], 201);
    }

    public function removeGalleryImage(Request $request, Community $community, int $index, ManageGallery $action): JsonResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $action->removeImage($community, $index);

        return response()->json(['message' => 'Image removed.']);
    }

    public function updateLevelPerks(Request $request, Community $community, UpdateLevelPerks $action): JsonResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $data = $request->validate([
            'perks'   => ['nullable', 'array'],
            'perks.*' => ['nullable', 'string', 'max:255'],
        ]);

        $action->execute($community, $data['perks'] ?? []);

        return response()->json(['message' => 'Level perks saved.']);
    }

    public function leaderboard(Request $request, Community $community, GetLeaderboard $query): JsonResponse
    {
        return response()->json($query->execute($community, $request->user()->id));
    }
}
