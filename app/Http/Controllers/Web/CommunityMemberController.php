<?php

namespace App\Http\Controllers\Web;

use App\Actions\Community\ChangeMemberRole;
use App\Actions\Community\ExtendMemberAccess;
use App\Actions\Community\RemoveMember;
use App\Actions\Community\ToggleMemberBlock;
use App\Http\Controllers\Controller;
use App\Models\Community;
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

    public function toggleBlock(Community $community, User $user, ToggleMemberBlock $action): RedirectResponse
    {
        $status = $action->execute(auth()->user(), $community, $user);

        return back()->with('success', "{$user->name} has been {$status}.");
    }

    public function extendAccess(Request $request, Community $community, ExtendMemberAccess $action): RedirectResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $data = $request->validate([
            'user_ids'   => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer'],
            'months'     => ['required', 'integer', 'min:1', 'max:120'],
        ]);

        $count = $action->execute($community, $data['user_ids'], $data['months']);

        return back()->with('success', "Extended access for {$count} member" . ($count !== 1 ? 's' : '') . " by {$data['months']} month" . ($data['months'] !== 1 ? 's' : '') . '.');
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
