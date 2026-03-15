<?php

namespace App\Http\Controllers\Api;

use App\Actions\Account\UpdateApiProfile;
use App\Http\Controllers\Controller;
use App\Models\CommunityMember;
use App\Models\User;
use App\Queries\Profile\GetProfileData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function me(Request $request, GetProfileData $query): JsonResponse
    {
        return $this->profileResponse($request->user(), isOwn: true, query: $query);
    }

    public function show(Request $request, string $username, GetProfileData $query): JsonResponse
    {
        $user  = User::where('username', $username)->firstOrFail();
        $isOwn = $request->user()->id === $user->id;

        return $this->profileResponse($user, isOwn: $isOwn, query: $query);
    }

    public function update(Request $request, UpdateApiProfile $action): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['sometimes', 'string', 'max:255'],
            'bio'      => ['sometimes', 'nullable', 'string', 'max:500'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'avatar'   => ['sometimes', 'nullable', 'url', 'max:500'],
        ]);

        $user = $action->execute($request->user(), $data);

        return response()->json([
            'message' => 'Profile updated.',
            'user'    => [
                'id'       => $user->id,
                'name'     => $user->name,
                'username' => $user->username,
                'bio'      => $user->bio,
                'location' => $user->location,
                'avatar'   => $user->avatar,
            ],
        ]);
    }

    private function profileResponse(User $user, bool $isOwn, GetProfileData $query): JsonResponse
    {
        $data        = $query->execute($user, $isOwn);
        $memberships = $data['memberships'];

        $membershipsMapped = $memberships->map(fn ($m) => [
            'community_id' => $m->community_id,
            'name'         => $m->community?->name,
            'slug'         => $m->community?->slug,
            'avatar'       => $m->community?->avatar,
            'role'         => $m->role,
            'points'       => $m->points,
            'level'        => CommunityMember::computeLevel($m->points),
            'joined_at'    => $m->joined_at,
        ]);

        return response()->json([
            'user'           => [
                'id' => $user->id, 'name' => $user->name, 'username' => $user->username,
                'bio' => $user->bio, 'avatar' => $user->avatar, 'location' => $user->location,
                'created_at' => $user->created_at,
            ],
            'is_own'         => $isOwn,
            'total_points'   => $data['total_points'],
            'level'          => $data['level'],
            'points_to_next' => $data['points_to_next'],
            'memberships'    => $membershipsMapped->values(),
            'activity_map'   => $data['activity_map'],
            'badges'         => $data['badges'],
        ]);
    }
}
