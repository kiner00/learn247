<?php

namespace App\Http\Controllers\Web;

use App\Actions\Community\ChangeMemberRole;
use App\Actions\Community\RemoveMember;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommunityMemberController extends Controller
{
    public function destroy(Community $community, User $user, RemoveMember $action): RedirectResponse
    {
        $action->execute(auth()->user(), $community, $user);

        return back()->with('success', 'Member removed.');
    }

    public function toggleBlock(Community $community, User $user): RedirectResponse
    {
        $actor = auth()->user();

        // Only owner or admin can block
        $actorMember = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $actor->id)
            ->first();

        abort_unless($actor->id === $community->owner_id || $actorMember?->canModerate(), 403);

        // Cannot block the community owner
        abort_if($user->id === $community->owner_id, 403);

        $member = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $member->update(['is_blocked' => ! $member->is_blocked]);

        $status = $member->is_blocked ? 'blocked' : 'unblocked';

        return back()->with('success', "{$user->name} has been {$status}.");
    }

    public function changeRole(
        Request $request,
        Community $community,
        User $user,
        ChangeMemberRole $action
    ): RedirectResponse {
        $request->validate(['role' => ['required', 'in:admin,moderator,member']]);

        $action->execute(auth()->user(), $community, $user, $request->input('role'));

        return back();
    }
}
