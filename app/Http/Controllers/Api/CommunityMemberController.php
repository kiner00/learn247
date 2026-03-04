<?php

namespace App\Http\Controllers\Api;

use App\Actions\Community\ChangeMemberRole;
use App\Actions\Community\RemoveMember;
use App\Http\Controllers\Controller;
use App\Http\Resources\CommunityMemberResource;
use App\Models\Community;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CommunityMemberController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $community = Community::where('slug', $request->query('community_slug'))->firstOrFail();
        $members   = $community->members()->with('user')->paginate(20);

        return CommunityMemberResource::collection($members);
    }

    public function destroy(Community $community, User $user, RemoveMember $action): JsonResponse
    {
        $action->execute(auth()->user(), $community, $user);

        return response()->json(['message' => 'Member removed.']);
    }

    public function changeRole(
        Request $request,
        Community $community,
        User $user,
        ChangeMemberRole $action
    ): CommunityMemberResource {
        $request->validate(['role' => 'required|in:admin,moderator,member']);
        $member = $action->execute(auth()->user(), $community, $user, $request->input('role'));

        return new CommunityMemberResource($member);
    }
}
