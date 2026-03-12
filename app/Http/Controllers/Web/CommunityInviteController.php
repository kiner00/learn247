<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\SendBatchInvites;
use App\Mail\CommunityInviteMail;
use App\Models\Community;
use App\Models\CommunityInvite;
use App\Models\CommunityMember;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CommunityInviteController extends Controller
{
    /**
     * Send invite(s) — single email or CSV batch.
     * Only community owner can call this.
     */
    public function store(Request $request, Community $community): JsonResponse|RedirectResponse
    {
        abort_if(auth()->id() !== $community->owner_id, 403);

        $isBatch = $request->hasFile('csv');

        if ($isBatch) {
            $request->validate(['csv' => 'file|mimes:csv,txt|max:2048']);

            $emails = [];
            $lines  = file($request->file('csv')->getPathname(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $col = trim(str_getcsv($line)[0] ?? '');
                if (filter_var($col, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = strtolower($col);
                }
            }
            $emails = array_unique($emails);

            if (empty($emails)) {
                $msg = 'No valid email addresses found in the CSV.';
                return $request->expectsJson()
                    ? response()->json(['message' => $msg], 422)
                    : back()->with('error', $msg);
            }

            SendBatchInvites::dispatch($community, $emails);

            $msg = count($emails) . ' invite' . (count($emails) !== 1 ? 's' : '') . ' queued — emails will arrive shortly.';
            return $request->expectsJson()
                ? response()->json(['message' => $msg])
                : back()->with('success', $msg);
        }

        // Single email — send immediately
        $request->validate(['email' => 'required|email']);
        $email = strtolower(trim($request->email));

        $alreadyMember = CommunityMember::where('community_id', $community->id)
            ->whereHas('user', fn ($q) => $q->where('email', $email))
            ->exists();

        if ($alreadyMember) {
            $msg = "{$email} is already a member.";
            return $request->expectsJson()
                ? response()->json(['message' => $msg], 422)
                : back()->with('error', $msg);
        }

        $invite = CommunityInvite::updateOrCreate(
            ['community_id' => $community->id, 'email' => $email],
            [
                'token'       => Str::random(64),
                'accepted_at' => null,
                'expires_at'  => now()->addDays(7),
            ]
        );

        Mail::to($email)->send(new CommunityInviteMail($invite));

        $msg = "Invite sent to {$email}.";
        return $request->expectsJson()
            ? response()->json(['message' => $msg])
            : back()->with('success', $msg);
    }

    /**
     * Accept an invite — anyone with the link can land here.
     * Not-logged-in users are redirected to login first.
     */
    public function accept(string $token): RedirectResponse
    {
        $invite = CommunityInvite::with('community')->where('token', $token)->firstOrFail();

        if (! auth()->check()) {
            return redirect()->route('login', ['redirect' => "/invite/{$token}"]);
        }

        $user      = auth()->user();
        $community = $invite->community;

        if ($invite->isExpired()) {
            return redirect()->route('communities.about', $community->slug)
                ->with('error', 'This invite link has expired.');
        }

        if ($invite->isAccepted()) {
            return redirect()->route('communities.show', $community->slug)
                ->with('info', 'This invite has already been accepted.');
        }

        if (strtolower($user->email) !== strtolower($invite->email)) {
            return redirect()->route('communities.index')
                ->with('error', "This invite was sent to {$invite->email}. Please log in with that account.");
        }

        // Add as community member (idempotent)
        CommunityMember::firstOrCreate(
            ['community_id' => $community->id, 'user_id' => $user->id],
            ['role' => CommunityMember::ROLE_MEMBER, 'joined_at' => now()]
        );

        // For paid communities, grant a complimentary active subscription
        if (! $community->isFree()) {
            Subscription::firstOrCreate(
                ['community_id' => $community->id, 'user_id' => $user->id],
                [
                    'status'     => Subscription::STATUS_ACTIVE,
                    'expires_at' => null, // no expiry for invited members
                ]
            );
        }

        $invite->update(['accepted_at' => now()]);

        return redirect()->route('communities.show', $community->slug)
            ->with('success', "Welcome to {$community->name}!");
    }
}
