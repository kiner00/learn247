<?php

namespace App\Http\Controllers\Web;

use App\Actions\Community\ChangeMemberRole;
use App\Actions\Community\ExtendMemberAccess;
use App\Actions\Community\RemoveMember;
use App\Actions\Community\ToggleMemberBlock;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeRoleRequest;
use App\Models\Community;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CommunityMemberController extends Controller
{
    public function destroy(Community $community, User $user, RemoveMember $action): RedirectResponse
    {
        try {
            $action->execute(auth()->user(), $community, $user);

            return back()->with('success', 'Member removed.');
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('CommunityMemberController@destroy failed', ['error' => $e->getMessage(), 'community_id' => $community->id, 'user_id' => $user->id]);
            return back()->with('error', 'Failed to remove member.');
        }
    }

    public function toggleBlock(Community $community, User $user, ToggleMemberBlock $action): RedirectResponse
    {
        try {
            $status = $action->execute(auth()->user(), $community, $user);

            return back()->with('success', "{$user->name} has been {$status}.");
        } catch (AuthorizationException|HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('CommunityMemberController@toggleBlock failed', ['error' => $e->getMessage(), 'community_id' => $community->id, 'user_id' => $user->id]);
            return back()->with('error', 'Failed to toggle block status.');
        }
    }

    public function extendAccess(Request $request, Community $community, ExtendMemberAccess $action): RedirectResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $data = $request->validate([
            'user_ids'   => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer'],
            'months'     => ['required', 'integer', 'min:1', 'max:120'],
        ]);

        try {
            $count = $action->execute($community, $data['user_ids'], $data['months']);

            return back()->with('success', "Extended access for {$count} member" . ($count !== 1 ? 's' : '') . " by {$data['months']} month" . ($data['months'] !== 1 ? 's' : '') . '.');
        } catch (\Throwable $e) {
            Log::error('CommunityMemberController@extendAccess failed', ['error' => $e->getMessage(), 'community_id' => $community->id]);
            return back()->with('error', 'Failed to extend access.');
        }
    }

    public function changeRole(
        ChangeRoleRequest $request,
        Community $community,
        User $user,
        ChangeMemberRole $action
    ): RedirectResponse {
        try {
            $action->execute(auth()->user(), $community, $user, $request->validated('role'));

            return back();
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('CommunityMemberController@changeRole failed', ['error' => $e->getMessage(), 'community_id' => $community->id, 'user_id' => $user->id]);
            return back()->with('error', 'Failed to change role.');
        }
    }
}
