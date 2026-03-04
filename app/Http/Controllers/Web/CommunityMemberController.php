<?php

namespace App\Http\Controllers\Web;

use App\Actions\Community\ChangeMemberRole;
use App\Actions\Community\RemoveMember;
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
