<?php

namespace App\Http\Controllers\Web;

use App\Actions\Community\AcceptInvite;
use App\Actions\Community\ProvisionInviteUser;
use App\Actions\Community\SendInvite;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\CommunityInvite;
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
            ->get()
            ->map(fn ($invite) => [
                'email'      => $invite->email,
                'status'     => $invite->isAccepted() ? 'accepted' : ($invite->isExpired() ? 'expired' : 'pending'),
                'sent_at'    => $invite->created_at->format('M j, Y'),
                'expires_at' => $invite->expires_at?->format('M j, Y'),
            ]);

        return response()->json($invites);
    }

    public function store(Request $request, Community $community, SendInvite $action): JsonResponse|RedirectResponse
    {
        abort_if(auth()->id() !== $community->owner_id, 403);

        if ($request->hasFile('csv')) {
            $request->validate(['csv' => 'file|mimes:csv,txt|max:2048']);
            $emails = $action->parseCSV($request->file('csv')->getPathname());
            $result = $action->batch($community, $emails);
        } else {
            $request->validate(['email' => 'required|email']);
            $result = $action->single($community, $request->email);
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

        $result    = $action->execute(auth()->user(), $invite);
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
