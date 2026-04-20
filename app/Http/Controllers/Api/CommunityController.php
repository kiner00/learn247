<?php

namespace App\Http\Controllers\Api;

use App\Actions\Community\CreateCommunity;
use App\Actions\Community\JoinCommunity;
use App\Actions\Community\ManageGallery;
use App\Actions\Community\SendAnnouncement;
use App\Actions\Community\StartTrialMembership;
use App\Actions\Community\UpdateCommunity;
use App\Actions\Community\UpdateLevelPerks;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCommunityRequest;
use App\Http\Resources\CommunityResource;
use App\Models\Community;
use App\Models\CommunityLevelPerk;
use App\Models\CommunityMember;
use App\Queries\Community\GetLeaderboard;
use App\Queries\Community\ListCommunities;
use App\Services\Analytics\CommunityAnalyticsService;
use App\Services\Community\MembershipAccessService;
use App\Services\Community\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

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
            $memberIds = Cache::remember(
                "user:{$userId}:community_ids",
                120, // 2 minutes
                fn () => CommunityMember::where('user_id', $userId)
                    ->pluck('community_id')
                    ->flip()
            );

            $communities->each(function (Community $c) use ($memberIds) {
                $c->is_member = $memberIds->has($c->id);
                $c->is_admin = false;
            });
        }

        return CommunityResource::collection($communities);
    }

    public function show(Request $request, Community $community, MembershipAccessService $membership): JsonResponse
    {
        $community->load('owner')->loadCount('members');

        $user = $request->user();
        $memberRecord = $user ? $community->members()->where('user_id', $user->id)->first() : null;
        $isOwner = $user && $community->owner_id === $user->id;
        $hasAccess = $user ? $membership->hasActiveMembership($user, $community) : false;

        return response()->json([
            'community' => new CommunityResource($community),
            'membership' => $memberRecord ? [
                'role' => $memberRecord->role,
                'points' => $memberRecord->points,
                'level' => CommunityMember::computeLevel($memberRecord->points),
                'joined_at' => $memberRecord->joined_at,
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
            'message' => 'Community created.',
            'community' => new CommunityResource($community),
        ], 201);
    }

    public function update(Request $request, Community $community, UpdateCommunity $action): JsonResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'in:Tech,Business,Design,Health,Education,Finance,Other'],
            'avatar' => ['nullable', 'image', 'max:15360'],
            'cover_image' => ['nullable', 'image', 'max:15360'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'in:PHP,USD'],
            'is_private' => ['boolean'],
            'affiliate_commission_rate' => ['nullable', 'integer', 'min:0', 'max:85'],
            'ai_chatbot_instructions' => ['nullable', 'string', 'max:10000'],
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

    public function join(Request $request, Community $community, JoinCommunity $join, StartTrialMembership $trial): JsonResponse
    {
        if ($community->isFree()) {
            $join->execute($request->user(), $community);

            return response()->json(['message' => 'You have joined the community!'], 201);
        }

        if ($community->hasTrial()) {
            $trial->execute($request->user(), $community);

            return response()->json(['message' => 'Your free trial has started!'], 201);
        }

        return response()->json([
            'message' => 'This is a paid community. Please subscribe to join.',
        ], 422);
    }

    public function about(Request $request, Community $community): JsonResponse
    {
        $community->load('owner:id,name,avatar,bio')->loadCount('members');

        $recentMembers = $community->members()->with('user:id,name,avatar')->latest()->take(8)->get()
            ->map(fn ($m) => ['name' => $m->user?->name, 'avatar' => $m->user?->avatar])
            ->filter(fn ($m) => $m['name'])->values();

        $gallery = collect($community->gallery_images ?? [])->values();

        return response()->json([
            'community' => new CommunityResource($community),
            'recent_members' => $recentMembers,
            'gallery' => $gallery,
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
            'members' => $members,
            'total_count' => $community->members()->count(),
            'admin_count' => $community->members()->where('role', 'admin')->count(),
        ]);
    }

    public function settings(Request $request, Community $community, PlanLimitService $planLimit): JsonResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $pricingGate = $planLimit->pricingGate($community);
        $levelPerks = CommunityLevelPerk::where('community_id', $community->id)->pluck('description', 'level')->toArray();

        return response()->json([
            'community' => new CommunityResource($community),
            'pricing_gate' => $pricingGate,
            'level_perks' => $levelPerks,
        ]);
    }

    public function analytics(Request $request, Community $community, CommunityAnalyticsService $analyticsService): JsonResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        return response()->json($analyticsService->build($community));
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
        $request->validate(['image' => ['required', 'image', 'max:15360']]);

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
            'perks' => ['nullable', 'array'],
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
