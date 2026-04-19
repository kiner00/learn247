<?php

namespace App\Http\Controllers\Web;

use App\Actions\Community\AcceptInvite;
use App\Actions\Community\ProvisionInviteUser;
use App\Actions\Community\SendInvite;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\CommunityInvite;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommunityInviteController extends Controller
{
    public function index(Community $community): JsonResponse
    {
        abort_if(auth()->id() !== $community->owner_id, 403);

        $invites = $community->invites()
            ->orderByDesc('created_at')
            ->get();

        $emails = $invites->where('accepted_at', '!=', null)->pluck('email')->unique();
        $userNames = User::whereIn('email', $emails)->pluck('name', 'email');

        $invites = $invites->map(fn ($invite) => [
            'email' => $invite->email,
            'name' => $invite->isAccepted() ? ($userNames[$invite->email] ?? null) : null,
            'status' => $invite->isAccepted() ? 'accepted' : ($invite->isExpired() ? 'expired' : 'pending'),
            'sent_at' => $invite->created_at->format('M j, Y'),
            'expires_at' => $invite->expires_at?->format('M j, Y'),
        ]);

        return response()->json($invites);
    }

    public function store(Request $request, Community $community, SendInvite $action): JsonResponse|RedirectResponse
    {
        abort_if(auth()->id() !== $community->owner_id, 403);

        $freeAccessMonths = $request->input('free_access_months')
            ? (int) $request->input('free_access_months')
            : null;

        if ($request->hasFile('csv')) {
            $request->validate([
                'csv' => 'file|mimes:csv,txt|max:2048',
                'free_access_months' => 'nullable|integer|min:1|max:120',
            ]);
            $emails = $action->parseCSV($request->file('csv')->getPathname());
            $result = $action->batch($community, $emails, $freeAccessMonths);
        } else {
            $request->validate([
                'email' => 'required|email',
                'free_access_months' => 'nullable|integer|min:1|max:120',
            ]);
            $result = $action->single($community, $request->email, $freeAccessMonths);
        }

        if ($request->expectsJson()) {
            return response()->json(
                ['message' => $result['message']],
                $result['type'] === 'error' ? 422 : 200
            );
        }

        return back()->with($result['type'], $result['message']);
    }

    public function accept(string $token, AcceptInvite $action, ProvisionInviteUser $provision): RedirectResponse
    {
        $invite = CommunityInvite::with('community')->where('token', $token)->firstOrFail();

        if (! auth()->check()) {
            $provision->execute($invite);
        }

        $result = $action->execute(auth()->user(), $invite);
        $community = $invite->community;

        $route = $result['redirect'] === 'show'
            ? route('communities.show', $community->slug)
            : route('communities.about', $community->slug);

        return redirect($route)->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }
}
