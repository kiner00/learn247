<?php

namespace App\Http\Controllers\Api;

use App\Actions\Community\ChangeMemberRole;
use App\Actions\Community\RemoveMember;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeRoleRequest;
use App\Http\Resources\CommunityMemberResource;
use App\Models\Community;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

class CommunityMemberController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $community = Community::where('slug', $request->query('community_slug'))->firstOrFail();
        $members = $community->members()->with('user')->paginate(20);

        return CommunityMemberResource::collection($members);
    }

    public function destroy(Community $community, User $user, RemoveMember $action): JsonResponse
    {
        try {
            $action->execute(auth()->user(), $community, $user);

            return response()->json(['message' => 'Member removed.']);
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Api\CommunityMemberController@destroy failed', ['error' => $e->getMessage(), 'community_id' => $community->id, 'user_id' => $user->id]);

            return response()->json(['message' => 'Failed to remove member.'], 500);
        }
    }

    public function changeRole(
        ChangeRoleRequest $request,
        Community $community,
        User $user,
        ChangeMemberRole $action
    ): CommunityMemberResource|JsonResponse {
        try {
            $member = $action->execute(auth()->user(), $community, $user, $request->validated('role'));

            return new CommunityMemberResource($member);
        } catch (\Throwable $e) {
            Log::error('Api\CommunityMemberController@changeRole failed', ['error' => $e->getMessage(), 'community_id' => $community->id, 'user_id' => $user->id]);

            return response()->json(['message' => 'Failed to change role.'], 500);
        }
    }
}
